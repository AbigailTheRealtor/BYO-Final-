<?php

namespace App\Http\Livewire\Landlord;

use Livewire\Component;
use App\Models\LandlordCounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandlordAgentAuctionCounterTerm extends Component
{

    public $pab;
    public $bidId;
    public $counterPrice;

    public $auctionId;
    public $user_id;
    public $parent_counter_id = null;
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;

    public $additional_details;

    public $services = [];
    public $proposedServices = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array

    // Client Contact Info
    public $client_name = '';
    public $client_phone = '';
    public $client_email = '';

    // Client Property Address
    public $client_property_address = '';
    public $client_property_city = '';
    public $client_property_state = '';
    public $client_property_zip = '';

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
    public $retainer_fee_option = '';                // yes | no
    public $retainer_fee_amount = '';                // $
    public $retainer_fee_application = '';           // applied | additional

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
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;
    public bool $isOfferListing = false;
    public $showEnhancements = false;
    public $showCustomEnhancement = false;
    public ?int $counterTermId = null;   // <— track existing record for edit
    public $lease_fee_flat = '';

    public $photo_enhancements = [];
    public $custom_enhancement = '';

    // Counter-specific deal negotiation fields
    public $desired_monthly_rent = '';
    public $availability_date = '';
    public $occupancy_status = '';
    public $flexibility = '';

    protected function rules(): array
    {
        $rules = [
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
        if ($this->isOfferListing) {
            $rules['client_name']             = ['required', 'string', 'max:255'];
            $rules['client_phone']            = ['required', 'string', 'max:50'];
            $rules['client_email']            = ['required', 'email', 'max:255'];
            $rules['client_property_address'] = ['required', 'string', 'max:255'];
            $rules['client_property_city']    = ['required', 'string', 'max:100'];
            $rules['client_property_state']   = ['required', 'string', 'max:100'];
            $rules['client_property_zip']     = ['required', 'string', 'max:20'];
        }
        return $rules;
    }

    protected array $messages = [
        'referral_fee_percent.numeric'        => 'Referral fee must be a number.',
        'referral_fee_percent.between'        => 'Referral fee must be between 0 and 100.',
        'client_name.required'                => 'Client name is required for offer listings.',
        'client_phone.required'               => 'Client phone is required for offer listings.',
        'client_email.required'               => 'Client email is required for offer listings.',
        'client_email.email'                  => 'Please enter a valid email address.',
        'client_property_address.required'    => 'Property street address is required for offer listings.',
        'client_property_city.required'       => 'Property city is required for offer listings.',
        'client_property_state.required'      => 'Property state is required for offer listings.',
        'client_property_zip.required'        => 'Property ZIP code is required for offer listings.',
    ];

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
    }

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
    public function mount($pab, $bidId, $parent_counter_id = null)
    {
        $this->pab              = $pab;
        $this->bidId            = $bidId;
        $this->parent_counter_id = $parent_counter_id ?: null;

        // property_type lives on the auction, not on the bid meta.
        // $pab may be either a LandlordAgentAuction (from counter_bid controller)
        // or a LandlordAgentAuctionBid (from LandlordCounteredTermsController).
        // Always resolve property_type from the auction record.
        $auctionId = $pab instanceof \App\Models\LandlordAgentAuction
            ? $pab->id
            : ($pab->landlord_agent_auction_id ?? null);

        // Store auction ID so the post-save redirect can use it.
        $this->auctionId = $auctionId;

        if ($auctionId) {
            $auc = \App\Models\LandlordAgentAuction::find($auctionId);
            $this->property_type = $auc ? ($auc->get->property_type ?? '') : '';
            $this->isListingCreatedByAgent = optional($auc)->isCreatedByAgent() ?? false;
            $this->isOfferListing = $auc ? ($auc->info('workflow_type') === 'offer_listing') : false;
        } else {
            $this->property_type = $pab->get->property_type ?? '';
        }

        // Always load proposed services from the agent's original bid (immutable reference)
        $agentBid = \App\Models\LandlordAgentAuctionBid::with('meta')->find($bidId);
        if ($agentBid) {
            $agentMeta = $agentBid->meta->pluck('meta_value', 'meta_key')->toArray();
            if (isset($agentMeta['services'])) {
                $raw = $agentMeta['services'];
                $rawProposed = is_string($raw) ? (json_decode($raw, true) ?? []) : (is_array($raw) ? $raw : []);
                $this->proposedServices = $this->filterServicesToCurrentCatalog(array_values(array_filter((array) $rawProposed)));
            }
        }

        $currentUserId = \Illuminate\Support\Facades\Auth::id();

        // EDIT MODE: Look for an active counter (status=1) THIS USER already submitted for this bid.
        // NOTE: landlord_counter_terms.landlord_agent_auction_id stores BID IDs (not auction IDs).
        // Only load status=1 (active) records — terminal or stale counters should not be reactivated via edit.
        $ownPending = LandlordCounterTerm::with('meta')
            ->where('landlord_agent_auction_id', $this->bidId)
            ->where('user_id', $currentUserId)
            ->where('status', 1)
            ->latest()
            ->first();

        if ($ownPending) {
            // Edit mode: let the user revise their own pending counter.
            $this->counterTermId = $ownPending->id;
            $this->hydrateFromMetaMap($ownPending->meta->pluck('meta_value', 'meta_key')->toArray());
            return;
        }

        // NEW COUNTER: Prefill from the latest counter submitted by the OTHER party so the
        // user sees the other side's most-recent terms as the negotiation baseline.
        $otherPartyLatest = LandlordCounterTerm::with('meta')
            ->where('landlord_agent_auction_id', $this->bidId)
            ->where('user_id', '!=', $currentUserId)
            ->latest()
            ->first();

        if ($otherPartyLatest) {
            $this->hydrateFromMetaMap($otherPartyLatest->meta->pluck('meta_value', 'meta_key')->toArray());
        } else {
            // No counter from the other party yet — prefill from the agent's original bid terms.
            $this->prefillFromAgentBid($this->bidId);
        }
        // New counters always start with a blank Additional Details field
        $this->additional_details = '';
    }

    /**
     * Prefill counter form with the agent's bid terms when creating a new counter.
     * Uses the agent's bid meta as the negotiation baseline.
     */
    private function prefillFromAgentBid(int $bidId): void
    {
        $bid = \App\Models\LandlordAgentAuctionBid::with('meta')->find($bidId);
        if (!$bid) {
            return;
        }
        $m = $bid->meta->pluck('meta_value', 'meta_key')->toArray();
        $this->hydrateFromMetaMap($m);
    }

    private function filterServicesToCurrentCatalog(array $services): array
    {
        $normalize = fn(string $s): string => mb_strtolower(trim(str_replace(
            ["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"],
            ["'",        "'",        '"',        '"'],
            $s
        )));

        $catalog = \App\Helpers\LandlordBidMatchScoreHelper::getCatalog($this->property_type ?: 'Residential Property');
        $catalogSet = array_flip($catalog); // catalog entries are already normalized

        $filtered = [];
        foreach ($services as $svc) {
            $norm = $normalize((string) $svc);
            if (isset($catalogSet[$norm])) {
                $filtered[] = $svc;
            }
        }
        return $filtered;
    }

    private function hydrateFromMetaMap(array $m): void
    {
        // Simple scalar/meta -> property mapping
        $assign = [
            'additional_details',
            'custom_enhancement',
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

            // === Lease Fee Flat (Interested-in-Selling flat-fee toggle) ===
            'lease_fee_flat',

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
            'retainer_fee_option',
            'retainer_fee_amount',
            'retainer_fee_application',

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
            'referral_fee_percent',
        ];


        foreach ($assign as $key) {
            if (array_key_exists($key, $m)) {
                $this->$key = $m[$key];
            }
        }

        // Arrays (JSON) — services, other_services, photo_enhancements
        if (isset($m['services'])) {
            $raw = $m['services'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $this->services = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $this->services = $raw;
            }
        }

        if (isset($m['other_services'])) {
            $raw = $m['other_services'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $this->other_services = is_array($decoded) ? array_values($decoded) : [];
            } elseif (is_array($raw)) {
                $this->other_services = array_values($raw);
            }
        }

        if (isset($m['photo_enhancements'])) {
            $raw = $m['photo_enhancements'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $this->photo_enhancements = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $this->photo_enhancements = $raw;
            }
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

        // Restore nested visibility flags from loaded data
        $this->showEnhancements = !empty($this->photo_enhancements)
            || in_array('Provide digital photo enhancements', $this->services);
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);

        // Counter-specific client contact and property fields
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
        if (array_key_exists('counter_desired_monthly_rent', $m)) {
            $this->desired_monthly_rent = $m['counter_desired_monthly_rent'];
        }
        if (array_key_exists('counter_availability_date', $m)) {
            $this->availability_date = $m['counter_availability_date'];
        }
        if (array_key_exists('counter_occupancy_status', $m)) {
            $this->occupancy_status = $m['counter_occupancy_status'];
        }
        if (array_key_exists('counter_flexibility', $m)) {
            $this->flexibility = $m['counter_flexibility'];
        }
    }

    public function render()
    {
        $flowKey = \App\Support\ServicesFormatter::keyForLandlordAgent($this->property_type ?: 'Residential');
        return view('livewire.landlord.landlord-agent-auction-counter-term', [
            'pab'             => $this->pab,
            'bidId'           => $this->bidId,
            'property_type'   => $this->property_type,
            'parent_counter_id' => $this->parent_counter_id,
            'groupedServices' => \App\Support\ServicesFormatter::orderSelectedServices($this->proposedServices, $flowKey),
        ])->extends('layouts.main')
            ->section('content');
    }


    public function submit()
    {
        $this->validate();

        try {
            DB::beginTransaction();

            if ($this->counterTermId) {
                // UPDATE same record
                $counterTerm = LandlordCounterTerm::findOrFail($this->counterTermId);
                // ensure base columns still correct if you want
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id ?: null,
                    'status' => 1,
                ]);
            } else {
                // landlord_counter_terms.landlord_agent_auction_id stores BID IDs (not auction IDs).
                $counterTerm = LandlordCounterTerm::create([
                    'user_id' => Auth::id(),
                    'landlord_agent_auction_id' => $this->bidId,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id ?: null,
                    'status' => 1,
                ]);

                $this->counterTermId = $counterTerm->id; // track after create
            }


            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();

            // Notify the other party that a counter bid was submitted.
            // $pab may be either a LandlordAgentAuction or a LandlordAgentAuctionBid.
            try {
                $bid = \App\Models\LandlordAgentAuctionBid::with('user')->find($this->bidId);
                if ($bid) {
                    $auctionId = $this->pab instanceof LandlordAgentAuction
                        ? $this->pab->id
                        : $this->pab->landlord_agent_auction_id;
                    $auction = LandlordAgentAuction::find($auctionId);
                    if ($auction) {
                        $senderId = Auth::id();
                        $recipientId = ($senderId === $auction->user_id)
                            ? $bid->user_id
                            : $auction->user_id;
                        $recipient = User::find($recipientId);
                        if ($recipient) {
                            $recipient->notify(new CounterBidSubmittedNotification(
                                $bid,
                                $auction,
                                Auth::user(),
                                $recipientId,
                                'landlord_agent'
                            ));
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send landlord counter terms notification', [
                    'bid_id' => $this->bidId,
                    'error'  => $e->getMessage(),
                ]);
            }

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('landlord.hire.agent.auction.bid.view-counter', $this->bidId);
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
        $counterTerm->saveMeta('photo_enhancements', json_encode($this->photo_enhancements ?? []));
        $counterTerm->saveMeta('custom_enhancement', $this->custom_enhancement ?? '');
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
        $counterTerm->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
        $counterTerm->saveMeta('purchase_type', $this->purchase_type);
        $counterTerm->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

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
        $counterTerm->saveMeta('retainer_fee_option', $this->retainer_fee_option);
        $counterTerm->saveMeta('retainer_fee_amount', str_replace(',', '', $this->retainer_fee_amount ?? ''));
        $counterTerm->saveMeta('retainer_fee_application', $this->retainer_fee_application);
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
        if ($this->isListingCreatedByAgent) {
            $counterTerm->saveMeta('referral_fee_percent', $this->referral_fee_percent);
        }

        // Offer-listing-only: client contact, property address, and landlord deal fields.
        // Only written when $isOfferListing is true to avoid polluting meta for
        // normal hire-agent counter bids with empty strings.
        if ($this->isOfferListing) {
            $counterTerm->saveMeta('counter_client_name', $this->client_name);
            $counterTerm->saveMeta('counter_client_phone', $this->client_phone);
            $counterTerm->saveMeta('counter_client_email', $this->client_email);
            $counterTerm->saveMeta('counter_property_address', $this->client_property_address);
            $counterTerm->saveMeta('counter_property_city', $this->client_property_city);
            $counterTerm->saveMeta('counter_property_state', $this->client_property_state);
            $counterTerm->saveMeta('counter_property_zip', $this->client_property_zip);
            $counterTerm->saveMeta('counter_desired_monthly_rent', $this->desired_monthly_rent);
            $counterTerm->saveMeta('counter_availability_date', $this->availability_date);
            $counterTerm->saveMeta('counter_occupancy_status', $this->occupancy_status);
            $counterTerm->saveMeta('counter_flexibility', $this->flexibility);
        }
    }
}
