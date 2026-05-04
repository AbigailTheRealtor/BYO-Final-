<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use App\Models\TenantCounterTerm;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Notifications\CounterBidSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantAgentAuctionCounterTerm extends Component
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


    // Tenant Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array

    // --- Broker Compensation (common to both blades) ---
    public $commission_structure = '';


    // Lease fee (commercial/residential variants)
    public $lease_fee_type = '';
    public $lease_fee_flat = '';
    public $lease_fee_percentage = ''; // residential: percentage of gross lease value
    public $lease_fee_percentage_monthly_rent = ''; // residential: % of monthly rent
    public $lease_fee_flat_combo = ''; // residential: flat + % (gross)
    public $lease_fee_percentage_combo = '';
    public $lease_fee_percentage_net = ''; // commercial: % of net aggregate rent
    public $lease_fee_flat_combo_net = ''; // commercial: flat + % (net)
    public $lease_fee_percentage_combo_net = '';
    public $lease_fee_other = '';

    public $lease_fee_flat_type = '$';

    // Purchase fee (both blades share same scheme)
    public $interested_purchase_fee_type = ''; // Yes | No
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';


    // Lease-Option Agreement (both blades)
    public $interested_lease_option_agreement = ''; // Yes | No
    public string $lease_type = 'percent'; // % | $
    public $lease_value = null; // numeric
    public string $purchase_type = 'percent'; // % | $
    public $purchase_value = null; // numeric


    // Terms & Agreements (both blades)
    public $protection_period = '';
    public $early_termination_fee_option = ''; // yes | no
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = ''; // yes | no
    public $retainer_fee_amount = '';
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;
    public $retainer_fee_application = ''; // applied | additional
    public $agency_agreement_timeframe = ''; // preset | custom
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';
    public $purchase_fee_flat_type = '$';
    public $purchase_fee_rental_period = '';

    // Payment Timing for Broker Fees
    public $broker_fee_timing = '';
    public $broker_fee_timing_other = '';
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';

    // Additional
    public $additional_details_broker = '';

    protected function rules(): array
    {
        return [
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected $messages = [
        'referral_fee_percent.numeric' => 'Referral fee must be a number.',
        'referral_fee_percent.between' => 'Referral fee must be between 0 and 100.',
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

    /**
     * Generic updated hook - guard against empty property names to prevent Str::studly() crash
     */
    public function updated($propertyName)
    {
        if (empty($propertyName)) {
            return;
        }
    }

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
    }

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


    public ?int $counterTermId = null;   // <— track existing record for edit

    public function mount($pab, $bidId)
    {
        $this->pab   = $pab;
        $this->bidId = $bidId;
        
        // Get property_type from the auction (listing) that this bid belongs to
        $auction = $pab->auction ?? null;
        if ($auction && $auction->get) {
            $this->property_type = $auction->get->property_type ?? '';
        } else {
            $this->property_type = $pab->get->property_type ?? '';
        }
        $this->isListingCreatedByAgent = optional($auction)->isCreatedByAgent() ?? false;

        // Check for existing active Tenant counter to determine if this is EDIT mode.
        // Only load status=1 (active) records — terminal or stale counters should not be reactivated via edit.
        $existingTenantCounter = TenantCounterTerm::with('meta')
            ->where('tenant_agent_auction_id', $this->pab->id)
            ->where('user_id', Auth::id())
            ->where('status', 1)
            ->latest()
            ->first();

        if ($existingTenantCounter) {
            // EDIT MODE: Tenant is editing their own counter - load their existing terms
            $this->counterTermId = $existingTenantCounter->id;
            $this->hydrateFromMetaMap($existingTenantCounter->meta->pluck('meta_value', 'meta_key')->toArray());
        } else {
            // NEW COUNTER: Prefill with the Agent's bid terms (other party's latest offer)
            $this->prefillFromAgentBid($pab);
            // New counters always start with a blank Additional Details field
            $this->additional_details = '';
        }
    }
    
    /**
     * Prefill form with Agent's bid terms when Tenant creates a new counter.
     * The Tenant edits the Agent's offer and sends back their version.
     */
    private function prefillFromAgentBid($bid): void
    {
        $bidData = $bid->get ?? null;
        if (!$bidData) {
            return;
        }
        
        // Map Agent bid meta keys to this component's properties
        $m = (array) $bidData;
        
        // Hydrate using the same logic as editing existing counter terms
        $this->hydrateFromMetaMap($m);
    }



    public function render()
    {
        return view('livewire.tenant.tenant-agent-auction-counter-term', [
            'pab'              => $this->pab,
            'bidId'            => $this->bidId,
            'property_type'    => $this->property_type,
            'parent_counter_id'=> $this->parent_counter_id,
            'servicesConfig'   => $this->servicesConfig,
        ])->extends('layouts.main')
            ->section('content');
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
            'lease_fee_percentage_monthly_rent',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_percentage_net',
            'lease_fee_flat_combo_net',
            'lease_fee_percentage_combo_net',
            'lease_fee_other',
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
            'broker_fee_timing',
            'broker_fee_timing_other',
            'broker_fee_days_from_rent',
            'broker_fee_days_after_lease',
            'broker_fee_days_after_rent',
            'referral_fee_percent',
        ];

        foreach ($assign as $key) {
            if (array_key_exists($key, $m)) {
                $this->$key = $m[$key];
            }
        }

        // Arrays (JSON) — services, other_services
        // Handle both string (from meta table) and array (from model accessor) values
        if (isset($m['services'])) {
            $services = $m['services'];
            if (is_string($services)) {
                $decoded = json_decode($services, true);
                $this->services = is_array($decoded) ? $decoded : [];
            } elseif (is_array($services)) {
                $this->services = $services;
            }
            // Filter to only services that belong to the current catalog for this property type.
            // This discards stale Buyer, mixed, or legacy catalog entries from old submissions.
            $this->services = $this->filterServicesToCurrentCatalog($this->services);
        }

        if (isset($m['other_services'])) {
            $otherServices = $m['other_services'];
            if (is_string($otherServices)) {
                $decoded = json_decode($otherServices, true);
                $this->other_services = is_array($decoded) ? array_values($decoded) : [];
            } elseif (is_array($otherServices)) {
                $this->other_services = array_values($otherServices);
            }
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

            // Canonicalize services before save.
            // Re-run the catalog filter so that only services belonging to the current
            // property-type catalog are saved. This removes any cross-role or
            // cross-property-type contamination that may have accumulated in
            // $this->services during the Livewire session (e.g. Buyer services that
            // were stored in the agent's bid and then surfaced through hydrateFromMetaMap).
            $this->services = $this->filterServicesToCurrentCatalog($this->services);

            // Integrity guard: log a warning if the canonical set is unexpectedly small
            // compared to what the bid offered so that silent drops can be investigated.
            $bid = \App\Models\TenantAgentAuctionBid::find($this->bidId);
            if ($bid) {
                $bidRaw = $bid->get->services ?? [];
                if (is_string($bidRaw)) $bidRaw = json_decode($bidRaw, true) ?? [];
                $bidBaseline = $this->filterServicesToCurrentCatalog(is_array($bidRaw) ? $bidRaw : []);
                $baselineCount = count($bidBaseline);
                $savedCount    = count($this->services);
                $dropped       = $baselineCount - $savedCount;
                if ($baselineCount > 0 && $dropped > (int) ceil($baselineCount * 0.5)) {
                    \Illuminate\Support\Facades\Log::warning('TenantCounterTerm: unusually large services drop on submit', [
                        'bid_id'          => $this->bidId,
                        'baseline_count'  => $baselineCount,
                        'saved_count'     => $savedCount,
                        'dropped_count'   => $dropped,
                        'user_id'         => \Illuminate\Support\Facades\Auth::id(),
                    ]);
                }
            }

            if ($this->counterTermId) {
                // UPDATE same record
                $counterTerm = TenantCounterTerm::findOrFail($this->counterTermId);
                // ensure base columns still correct if you want
                $counterTerm->update([
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                    'status' => 1,
                ]);
            } else {
                // CREATE new record
                $counterTerm = TenantCounterTerm::create([
                    'user_id' => Auth::id(),
                    'tenant_agent_auction_id' => $this->pab->id,
                    'property_type' => $this->property_type,
                    'parent_counter_id' => $this->parent_counter_id,
                    'status' => 1,
                ]);
                $this->counterTermId = $counterTerm->id; // track after create
            }

            // 2. Save all meta data
            $this->saveAllMetaData($counterTerm);

            DB::commit();
            
            // Send notification to the agent whose bid is being countered
            try {
                $agentId = $this->pab->user_id;
                $auction = TenantAgentAuction::find($this->pab->tenant_agent_auction_id);
                if ($agentId && $auction) {
                    $agent = User::find($agentId);
                    if ($agent) {
                        $agent->notify(new CounterBidSubmittedNotification(
                            $this->pab,
                            $auction,
                            Auth::user(),
                            $agentId
                        ));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send counter terms notification', [
                    'bid_id' => $this->bidId,
                    'error' => $e->getMessage()
                ]);
            }

            session()->flash('success', $this->counterTermId ? 'Counter terms updated!' : 'Counter terms submitted!');
            return redirect()->route('tenant.agent.auction.view', $this->pab->tenant_agent_auction_id);
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
        $counterTerm->saveMeta('lease_fee_flat_type', $this->lease_fee_flat_type);


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

        // Payment Timing for Broker Fees
        $counterTerm->saveMeta('broker_fee_timing', $this->broker_fee_timing);
        $counterTerm->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
        $counterTerm->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
        $counterTerm->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
        $counterTerm->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);

        // Additional Details
        $counterTerm->saveMeta('additional_details_broker', $this->additional_details_broker ?? null);
        if ($this->isListingCreatedByAgent) {
            $counterTerm->saveMeta('referral_fee_percent', $this->referral_fee_percent);
        }
    }
}
