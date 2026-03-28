<?php

namespace App\Http\Livewire\Seller;

use Livewire\Component;
use App\Models\SellerCounterTerm;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SellerAgentAuctionCounterTerm extends Component
{
    public $pab;
    public $bidId;
    public $counterTermId = null;
    public $auctionId;
    public $property_type = '';
    public $activeTab = 0;

    // Broker Compensation
    public $commission_structure = '';
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';
    public $interested_purchase_fee_type = '';
    public $nominal = '';

    // Buyer's Broker Commission Fee conditional fields
    public $commission_structure_type = '';
    public $commission_structure_type_fee_flat = '';
    public $commission_structure_type_fee_percentage = '';
    public $commission_structure_type_fee_percentage_combo = '';
    public $commission_structure_type_fee_flat_combo = '';
    public $commission_structure_type_fee_other = '';

    // Lease-Option Agreement
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent';
    public $lease_value = '';
    public $purchase_type = 'percent';
    public $purchase_value = '';

    // Retained Deposits
    public $retained_deposits = '';

    // Seller-specific leasing compensation
    public $seller_leasing_fee_type = '';
    public $seller_leasing_gross = '';
    public $seller_leasing_gross_rental = '';
    public $seller_leasing_gross_percentage = '';
    public $seller_leasing_gross_flat_combo = '';
    public $seller_leasing_gross_percentage_combo = '';
    public $seller_leasing_gross_percentage_net_combo = '';
    public $seller_leasing_gross_flat_net_combo = '';
    public $seller_leasing_gross_other = '';
    public $seller_leasing_each_rental = '';
    public $seller_leasing_gross_month_rent = '';
    public $seller_leasing_gross_no_of_months = '';
    public $seller_leasing_gross_purchase_fee_flat_amount = '';
    public $seller_leasing_gross_purchase_fee_other = '';
    public $sales_tax_option_gross = '';
    public $seller_leasing_gross_sales_tax_first_month = '';
    public $seller_leasing_gross_sales_tax_flat_free_gross = '';
    public $seller_leasing_gross_sales_tax_option_gross = '';

    // Agency Agreement
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';

    // Additional Details
    public $additional_details = '';

    // Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = [];

    // Services UI toggles (required by services partial)
    public bool $showEnhancements = false;
    public bool $showCustomEnhancement = false;
    public bool $showOpenHouseInput = false;

    // Services sub-fields (required by services partial)
    public $photo_enhancements = [];
    public $custom_enhancement = '';
    public $openHouseCount = '';

    public function setActiveTab($index)
    {
        $this->activeTab = (int) $index;
    }

    public function setType(string $which, string $type): void
    {
        if ($which === 'lease') {
            $this->lease_type = $type ?: 'percent';
        } else {
            $this->purchase_type = $type ?: 'percent';
        }
    }

    public function updatedEarlyTerminationFeeOption($value)
    {
        if ($value !== 'yes') {
            $this->early_termination_fee_amount = '';
        }
    }

    public function updatedRetainerFeeOption($value)
    {
        if ($value !== 'yes') {
            $this->retainer_fee_amount = '';
            $this->retainer_fee_application = '';
        }
    }

    public function updatedAgencyAgreementTimeframe($value)
    {
        if ($value !== 'custom') {
            $this->agency_agreement_custom = '';
        }
    }

    public function updatedOtherServicesEnabled($enabled): void
    {
        if ($enabled && empty($this->other_services)) {
            $this->other_services = [''];
        }
    }

    public function addServiceField(): void
    {
        $this->other_services[] = '';
    }

    public function removeService(int $index): void
    {
        unset($this->other_services[$index]);
        $this->other_services = array_values($this->other_services);
    }

    public function mount($pab, $bidId)
    {
        $this->pab   = $pab;
        $this->bidId = $bidId;

        $auction = $pab->auction ?? SellerAgentAuction::find($pab->seller_agent_auction_id ?? null);
        if ($auction && $auction->get) {
            $this->property_type = $auction->get->property_type ?? '';
            $this->auctionId     = $auction->id;
        } else {
            $this->property_type = $pab->get->property_type ?? '';
            $this->auctionId     = $pab->seller_agent_auction_id ?? null;
        }

        // Check for existing Seller counter to determine if this is EDIT mode
        $existing = SellerCounterTerm::with('meta')
            ->where('seller_agent_auction_bid_id', $this->pab->id)
            ->where('user_id', Auth::id())
            ->latest()
            ->first();

        if ($existing) {
            $this->counterTermId = $existing->id;
            $this->hydrateFromMetaMap($existing->meta->pluck('meta_value', 'meta_key')->toArray());
        } else {
            // Pre-fill from the agent's bid so the listing owner sees current terms and can edit
            $this->prefillFromAgentBid($pab);
        }
    }

    private function prefillFromAgentBid($bid): void
    {
        $bidData = $bid->get ?? null;
        if (!$bidData) {
            return;
        }
        $this->hydrateFromMetaMap((array) $bidData);
    }

    private function hydrateFromMetaMap(array $m): void
    {
        $assign = [
            'commission_structure',
            'purchase_fee_type',
            'purchase_fee_flat',
            'purchase_fee_percentage',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other',
            'interested_purchase_fee_type',
            'nominal',
            // Buyer's Broker Commission Fee
            'commission_structure_type',
            'commission_structure_type_fee_flat',
            'commission_structure_type_fee_percentage',
            'commission_structure_type_fee_percentage_combo',
            'commission_structure_type_fee_flat_combo',
            'commission_structure_type_fee_other',
            // Lease fields
            'seller_leasing_fee_type',
            'seller_leasing_gross',
            'seller_leasing_gross_rental',
            'seller_leasing_gross_percentage',
            'seller_leasing_gross_flat_combo',
            'seller_leasing_gross_percentage_combo',
            'seller_leasing_gross_percentage_net_combo',
            'seller_leasing_gross_flat_net_combo',
            'seller_leasing_gross_other',
            'seller_leasing_each_rental',
            'seller_leasing_gross_month_rent',
            'seller_leasing_gross_no_of_months',
            'seller_leasing_gross_purchase_fee_flat_amount',
            'seller_leasing_gross_purchase_fee_other',
            'sales_tax_option_gross',
            'seller_leasing_gross_sales_tax_first_month',
            'seller_leasing_gross_sales_tax_flat_free_gross',
            'seller_leasing_gross_sales_tax_option_gross',
            // Lease-Option
            'interested_lease_option_agreement',
            'lease_type',
            'lease_value',
            'purchase_type',
            'purchase_value',
            // Retained Deposits
            'retained_deposits',
            // Agency Agreement
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
            'additional_details',
            'other_services_enabled',
            'custom_enhancement',
            'openHouseCount',
        ];

        foreach ($assign as $key) {
            if (array_key_exists($key, $m)) {
                $this->$key = $m[$key];
            }
        }

        if (isset($m['services'])) {
            $s = $m['services'];
            if (is_string($s)) {
                $decoded = json_decode($s, true);
                $this->services = is_array($decoded) ? $decoded : [];
            } elseif (is_array($s)) {
                $this->services = $s;
            }
        }

        if (isset($m['photo_enhancements'])) {
            $pe = $m['photo_enhancements'];
            if (is_string($pe)) {
                $decoded = json_decode($pe, true);
                $this->photo_enhancements = is_array($decoded) ? $decoded : [];
            } elseif (is_array($pe)) {
                $this->photo_enhancements = $pe;
            }
        }

        if (isset($m['other_services'])) {
            $os = $m['other_services'];
            if (is_string($os)) {
                $decoded = json_decode($os, true);
                $this->other_services = is_array($decoded) ? array_values($decoded) : [];
            } elseif (is_array($os)) {
                $this->other_services = array_values($os);
            }
        }

        if (isset($m['other_services_enabled'])) {
            $this->other_services_enabled = filter_var($m['other_services_enabled'], FILTER_VALIDATE_BOOLEAN)
                || $m['other_services_enabled'] === '1';
        }
    }

    public function render()
    {
        return view('livewire.seller.seller-agent-auction-counter-term', [
            'pab'         => $this->pab,
            'bidId'       => $this->bidId,
            'property_type' => $this->property_type,
        ])->extends('layouts.main')
            ->section('content');
    }

    public function submit()
    {
        try {
            DB::beginTransaction();

            if ($this->counterTermId) {
                $counterTerm = SellerCounterTerm::findOrFail($this->counterTermId);
                $counterTerm->update([
                    'property_type'    => $this->property_type,
                    'parent_counter_id' => null,
                ]);
            } else {
                $counterTerm = SellerCounterTerm::create([
                    'user_id'                     => Auth::id(),
                    'seller_agent_auction_bid_id' => $this->pab->id,
                    'seller_agent_auction_id'     => $this->auctionId,
                    'property_type'               => $this->property_type,
                    'status'                      => 'pending',
                ]);
                $this->counterTermId = $counterTerm->id;
            }

            $this->saveAllMetaData($counterTerm);

            DB::commit();

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('seller.agent.auction.detail', ['id' => $this->auctionId]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Seller counter term save failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Error saving counter bid: ' . $e->getMessage());
        }
    }

    private function saveAllMetaData($counterTerm)
    {
        // Seller's Broker Purchase Fee
        $counterTerm->saveMeta('commission_structure', $this->commission_structure);
        $counterTerm->saveMeta('purchase_fee_type', $this->purchase_fee_type);
        $counterTerm->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
        $counterTerm->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
        $counterTerm->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
        $counterTerm->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
        $counterTerm->saveMeta('purchase_fee_other', $this->purchase_fee_other);
        $counterTerm->saveMeta('interested_purchase_fee_type', $this->interested_purchase_fee_type);
        $counterTerm->saveMeta('nominal', $this->nominal);

        // Buyer's Broker Commission Fee
        $counterTerm->saveMeta('commission_structure_type', $this->commission_structure_type);
        $counterTerm->saveMeta('commission_structure_type_fee_flat', $this->commission_structure_type_fee_flat);
        $counterTerm->saveMeta('commission_structure_type_fee_percentage', $this->commission_structure_type_fee_percentage);
        $counterTerm->saveMeta('commission_structure_type_fee_percentage_combo', $this->commission_structure_type_fee_percentage_combo);
        $counterTerm->saveMeta('commission_structure_type_fee_flat_combo', $this->commission_structure_type_fee_flat_combo);
        $counterTerm->saveMeta('commission_structure_type_fee_other', $this->commission_structure_type_fee_other);

        // Lease fields
        $counterTerm->saveMeta('seller_leasing_fee_type', $this->seller_leasing_fee_type);
        $counterTerm->saveMeta('seller_leasing_gross', $this->seller_leasing_gross);
        $counterTerm->saveMeta('seller_leasing_gross_rental', $this->seller_leasing_gross_rental);
        $counterTerm->saveMeta('seller_leasing_gross_percentage', $this->seller_leasing_gross_percentage);
        $counterTerm->saveMeta('seller_leasing_gross_flat_combo', $this->seller_leasing_gross_flat_combo);
        $counterTerm->saveMeta('seller_leasing_gross_percentage_combo', $this->seller_leasing_gross_percentage_combo);
        $counterTerm->saveMeta('seller_leasing_gross_percentage_net_combo', $this->seller_leasing_gross_percentage_net_combo);
        $counterTerm->saveMeta('seller_leasing_gross_flat_net_combo', $this->seller_leasing_gross_flat_net_combo);
        $counterTerm->saveMeta('seller_leasing_gross_other', $this->seller_leasing_gross_other);
        $counterTerm->saveMeta('seller_leasing_each_rental', $this->seller_leasing_each_rental);
        $counterTerm->saveMeta('seller_leasing_gross_month_rent', $this->seller_leasing_gross_month_rent);
        $counterTerm->saveMeta('seller_leasing_gross_no_of_months', $this->seller_leasing_gross_no_of_months);
        $counterTerm->saveMeta('seller_leasing_gross_purchase_fee_flat_amount', $this->seller_leasing_gross_purchase_fee_flat_amount);
        $counterTerm->saveMeta('seller_leasing_gross_purchase_fee_other', $this->seller_leasing_gross_purchase_fee_other);
        $counterTerm->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
        $counterTerm->saveMeta('seller_leasing_gross_sales_tax_first_month', $this->seller_leasing_gross_sales_tax_first_month);
        $counterTerm->saveMeta('seller_leasing_gross_sales_tax_flat_free_gross', $this->seller_leasing_gross_sales_tax_flat_free_gross);
        $counterTerm->saveMeta('seller_leasing_gross_sales_tax_option_gross', $this->seller_leasing_gross_sales_tax_option_gross);

        // Lease-Option Agreement
        $counterTerm->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
        $counterTerm->saveMeta('lease_type', $this->lease_type);
        $counterTerm->saveMeta('lease_value', $this->lease_value);
        $counterTerm->saveMeta('purchase_type', $this->purchase_type);
        $counterTerm->saveMeta('purchase_value', $this->purchase_value);

        // Retained Deposits
        $counterTerm->saveMeta('retained_deposits', $this->retained_deposits);

        // Agency Agreement
        $counterTerm->saveMeta('protection_period', $this->protection_period);
        $counterTerm->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
        $counterTerm->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
        $counterTerm->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $counterTerm->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
        $counterTerm->saveMeta('retainer_fee_application', $this->retainer_fee_application);
        $counterTerm->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
        $counterTerm->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
        $counterTerm->saveMeta('brokerage_relationship', $this->brokerage_relationship);
        $counterTerm->saveMeta('additional_details_broker', $this->additional_details_broker);

        $counterTerm->saveMeta('additional_details', $this->additional_details);

        $counterTerm->saveMeta('services', json_encode($this->services));
        $counterTerm->saveMeta('other_services_enabled', $this->other_services_enabled ? '1' : '0');
        $counterTerm->saveMeta('other_services', json_encode($this->other_services ?? []));
        $counterTerm->saveMeta('photo_enhancements', json_encode($this->photo_enhancements ?? []));
        $counterTerm->saveMeta('custom_enhancement', $this->custom_enhancement ?? '');
        $counterTerm->saveMeta('openHouseCount', $this->openHouseCount ?? '');
    }
}
