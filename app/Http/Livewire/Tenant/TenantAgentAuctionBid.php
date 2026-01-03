<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\TenantAgentAuctionBid as TenantAgentAuctionBidData;
use Illuminate\Support\Facades\DB;

class TenantAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $editBidId = null;
    public $isEditMode = false;
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;

    // Agent Information
    public $first_name;
    public $last_name;
    public $phone;
    public $email;
    public $brokerage;
    public $license_no;
    public $year_licensed;
    public $nar_id;

    // Agent Overview
    public $bio;
    public $why_hire_you;
    public $what_sets_you_apart;
    public $marketing_plan;
    public $reviews_links = [];
    public $website_link;
    public $social_media = [['platform' => '', 'text' => '']];


    // Tenant Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;

    // Additional Details
    public $additional_details;

    public $commission_structure;
    public $lease_fee_type;
    public $lease_fee_flat_type = '$';
    public $lease_fee_flat;
    public $lease_fee_percentage;
    public $lease_fee_percentage_monthly_rent;
    public $lease_fee_percentage_monthly_number;
    public $lease_fee_flat_combo;
    public $lease_fee_percentage_combo;
    public $lease_fee_percentage_net;
    public $lease_fee_flat_combo_net;
    public $lease_fee_percentage_combo_net;
    public $lease_fee_other;

    public $interested_purchase_fee_type;
    public $purchase_fee_type;
    public $purchase_fee_flat_type = '$';
    public $purchase_fee_flat;
    public $purchase_fee_percentage;
    public $purchase_fee_percentage_combo;
    public $purchase_fee_flat_combo;
    public $purchase_fee_other;

    public $interested_lease_option_agreement;
    public $lease_type = 'percent';
    public $lease_value;
    public $purchase_type = 'percent';
    public $purchase_value;

    public $protection_period;
    public $early_termination_fee_option;
    public $early_termination_fee_amount;
    public $retainer_fee_option;
    public $retainer_fee_amount;
    public $retainer_fee_application;
    public $agency_agreement_timeframe;
    public $agency_agreement_custom;
    public $brokerage_relationship;
    public $additional_details_broker;

    // Broker Fee Timing
    public $broker_fee_timing;
    public $broker_fee_days_from_rent;
    public $broker_fee_days_after_lease;
    public $broker_fee_days_after_rent;
    public $broker_fee_timing_other;

    // Presentation & Promotional Materials
    public $presentation_link;
    public $video_upload;
    public $business_card_link;
    public $business_card;
    public $promo_material_type;
    public $promo_materials = [];
    public $promo_materials_link;
    public array $promoMaterials = [];



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

    protected function rules(): array
    {
        return [
            'commission_structure' => 'required|string',
            'lease_fee_type' => 'required|string',
            'promoMaterials.*.type'  => ['nullable', 'string'],
            'promoMaterials.*.other' => ['nullable', 'string'],
            'promoMaterials.*.text'  => ['nullable', 'string'],
            'promoMaterials.*.files' => ['nullable', 'array'],
            'promoMaterials.*.files.*' => [
                'file',
                'max:51200', // 50MB each
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,ppt,pptx'
            ],
            'year_licensed' => 'required|numeric|min:1900|max:' . date('Y'),

        ];
    }

    protected $messages = [
        'commission_structure.required' => 'Please select a Tenant\'s Broker Commission Structure.',
        'lease_fee_type.required' => 'Please select a Tenant\'s Broker Lease Fee.',
    ];

    public function updatePlaceholder($index, $platform)
    {
        // Define possible platforms and their corresponding placeholder texts
        $placeholders = [
            'Facebook' => 'Enter profile link (e.g., https://www.facebook.com/yourhandle)',
            'Instagram' => 'Enter profile link (e.g., https://www.instagram.com/yourhandle)',
            'LinkedIn' => 'Enter profile link (e.g., https://www.linkedin.com/in/yourhandle)',
            'TikTok' => 'Enter profile link (e.g., https://www.tiktok.com/@yourhandle)',
            'X' => 'Enter profile link (e.g., https://www.x.com/yourhandle)',
            'YouTube' => 'Enter profile link (e.g., https://www.youtube.com/c/yourchannel)',
        ];

        // Set the placeholder for the corresponding platform
        $this->social_media[$index]['placeholder'] = $placeholders[$platform] ?? 'Enter profile link';
    }

    public function addSocialMedia()
    {
        $this->social_media[] = ['platform' => '', 'text' => '', 'placeholder' => 'Enter profile link'];
    }

    public function removeSocialMedia($index)
    {
        unset($this->social_media[$index]);
        $this->social_media = array_values($this->social_media); // Re-index array after removal
    }



    public function updated($name, $value): void
    {
        // Guard against empty property name to prevent Str::studly() crash
        if (empty($name)) {
            return;
        }
        
        // If a type is changed away from 'Other', clear its 'other' text.
        if (str_ends_with($name, '.type')) {
            [$root, $idx] = explode('.', str_replace('promoMaterials.', '', $name), 2);
            // $root is index, but easier: parse by pieces:
            $parts = explode('.', $name); // ['promoMaterials', '0', 'type']
            $i = (int) $parts[1];
            if (($this->promoMaterials[$i]['type'] ?? '') !== 'Other') {
                $this->promoMaterials[$i]['other'] = '';
            }
        }
    }
    public function addMaterial(): void
    {
        // Clear all file upload errors for promoMaterials
        $errorBag = $this->getErrorBag();
        foreach ($errorBag->keys() as $key) {
            if (str_starts_with($key, 'promoMaterials.') && str_contains($key, '.files')) {
                $this->resetErrorBag($key);
            }
        }
        
        $new = [
            'type'  => '',
            'other' => '',
            'link'  => '',
            'files' => [],
        ];

        // PUSH to the end (so new entry appears last)
        $this->promoMaterials[] = $new;

        // Re-index (optional, keeps indices clean after deletes)
        $this->promoMaterials = array_values($this->promoMaterials);
    }

    public function removeMaterial(int $index): void
    {
        if (count($this->promoMaterials) > 1) {
            array_splice($this->promoMaterials, $index, 1);
            $this->promoMaterials = array_values($this->promoMaterials);
        }
    }

    public function clearFileError(int $index): void
    {
        $this->resetErrorBag("promoMaterials.{$index}.files");
        $this->resetErrorBag("promoMaterials.{$index}.files.*");
    }


    // public function addService()
    // {
    //     $this->custom_services[] = [
    //         'fee' => null,
    //         'description' => '',
    //         'marketing_fee' => null,
    //         'marketing_description' => ''
    //     ];
    // }

    // public function removeService($index)
    // {
    //     unset($this->custom_services[$index]);
    //     $this->custom_services = array_values($this->custom_services);
    //     $this->calculateTotals();
    // }

    // public function calculateTotals()
    // {
    //     $this->total_flat_fee = collect($this->custom_services)
    //         ->sum(function ($service) {
    //             return $service['fee'] ?? 0;
    //         });

    //     $this->total_marketing_fee = collect($this->custom_services)
    //         ->sum(function ($service) {
    //             return $service['marketing_fee'] ?? 0;
    //         });
    // }

    // public function updatedCustomServices()
    // {
    //     $this->calculateTotals();
    // }

    // public function add_flat_fee_service()
    // {
    //     $this->flat_fee_services[] = [
    //         'description' => '',
    //         'fee' => 0
    //     ];
    // }

    // public function remove_flat_fee_service($index)
    // {
    //     unset($this->flat_fee_services[$index]);
    //     $this->flat_fee_services = array_values($this->flat_fee_services); // Reindex array
    // }
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
    public function addReviewLink()
    {
        $this->reviews_links[] = ['text' => '']; // Adds a new blank input field
    }

    public function removeReviewLink($index)
    {
        if (count($this->reviews_links) > 1) {
            array_splice($this->reviews_links, $index, 1); // Removes the selected entry
        }
    }
    // Add this method to your component class

    public function mount($auctionId = null)
    {
        $this->editBidId = request()->query('edit');
        $this->isEditMode = !empty($this->editBidId);

        $this->promoMaterials = [
            [
                'type'  => '',
                'other' => '',
                'link'  => '',
                'files' => [],
            ],
        ];


        $auction = \App\Models\TenantAgentAuction::find($auctionId);
        
        if (!$auction) {
            session()->flash('error', 'Auction not found.');
            return redirect()->route('home');
        }
        
        $endDate = strtotime($auction->end_date . ' ' . ($auction->end_time ?? '23:59:59'));
        $isExpired = time() > $endDate;
        
        if ($isExpired) {
            session()->flash('error', 'This auction has ended. Bidding is no longer available.');
            return redirect()->route('tenant.agent.auction.view', $auctionId);
        }
        
        if ($auction->is_sold) {
            session()->flash('error', 'This listing has been sold. Bidding is no longer available.');
            return redirect()->route('tenant.agent.auction.view', $auctionId);
        }
        // $this->additional_details = $auction->get->additional_details ?? '';

        $this->services = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;
        $this->other_services = is_string($auction->get->other_services) ? json_decode($auction->get->other_services, true) ?? [] : (array)$auction->get->other_services;

        $this->other_services_enabled = $auction->get->other_services_enabled;
        $this->service_type = $auction->get->service_type;
        $this->user_type = $auction->get->user_type ?? '';
        $this->property_type = $auction->get->property_type ?? '';
        // Load broker compensation fields
        $this->commission_structure = $auction->get->commission_structure ?? '';
        $this->lease_fee_type = $auction->get->lease_fee_type ?? '';
        $this->lease_fee_flat_type = $auction->get->lease_fee_flat_type ?? '$';
        $this->lease_fee_flat = $auction->get->lease_fee_flat ?? '';
        $this->lease_fee_percentage = $auction->get->lease_fee_percentage ?? '';
        $this->lease_fee_percentage_monthly_rent = $auction->get->lease_fee_percentage_monthly_rent ?? '';
        $this->lease_fee_percentage_monthly_number = $auction->get->lease_fee_percentage_monthly_number ?? '';
        $this->lease_fee_flat_combo = $auction->get->lease_fee_flat_combo ?? '';
        $this->lease_fee_percentage_combo = $auction->get->lease_fee_percentage_combo ?? '';
        $this->lease_fee_percentage_net = $auction->get->lease_fee_percentage_net ?? '';
        $this->lease_fee_flat_combo_net = $auction->get->lease_fee_flat_combo_net ?? '';
        $this->lease_fee_percentage_combo_net = $auction->get->lease_fee_percentage_combo_net ?? '';
        $this->lease_fee_other = $auction->get->lease_fee_other ?? '';

        // Purchase fields
        $this->interested_purchase_fee_type = $auction->get->interested_purchase_fee_type ?? '';
        $this->purchase_fee_type = $auction->get->purchase_fee_type ?? '';
        $this->purchase_fee_flat_type = $auction->get->purchase_fee_flat_type ?? '$';
        $this->purchase_fee_flat = $auction->get->purchase_fee_flat ?? '';
        $this->purchase_fee_percentage = $auction->get->purchase_fee_percentage ?? '';
        $this->purchase_fee_percentage_combo = $auction->get->purchase_fee_percentage_combo ?? '';
        $this->purchase_fee_flat_combo = $auction->get->purchase_fee_flat_combo ?? '';
        $this->purchase_fee_other = $auction->get->purchase_fee_other ?? '';

        // Lease Option Agreement
        $this->interested_lease_option_agreement = $auction->get->interested_lease_option_agreement ?? '';
        $this->lease_type = $auction->get->lease_type ?? 'percent';
        $this->lease_value = $auction->get->lease_value ?? '';
        $this->purchase_type = $auction->get->purchase_type ?? 'percent';
        $this->purchase_value = $auction->get->purchase_value ?? '';

        // Other Brokerage Fields
        $this->protection_period = $auction->get->protection_period ?? '';
        $this->early_termination_fee_option = $auction->get->early_termination_fee_option ?? '';
        $this->early_termination_fee_amount = $auction->get->early_termination_fee_amount ?? '';
        $this->retainer_fee_option = $auction->get->retainer_fee_option ?? '';
        $this->retainer_fee_amount = $auction->get->retainer_fee_amount ?? '';
        $this->retainer_fee_application = $auction->get->retainer_fee_application ?? '';
        $this->agency_agreement_timeframe = $auction->get->agency_agreement_timeframe ?? '';
        $this->agency_agreement_custom = $auction->get->agency_agreement_custom ?? '';
        $this->brokerage_relationship = $auction->get->brokerage_relationship ?? '';
        $this->additional_details_broker = $auction->get->additional_details_broker ?? '';

        // Auto-fill Broker Fee Timing fields from listing (Residential + Commercial)
        $this->broker_fee_timing = $auction->get->broker_fee_timing ?? '';
        $this->broker_fee_days_from_rent = $auction->get->broker_fee_days_from_rent ?? '';
        $this->broker_fee_days_after_lease = $auction->get->broker_fee_days_after_lease ?? '';
        $this->broker_fee_days_after_rent = $auction->get->broker_fee_days_after_rent ?? '';
        $this->broker_fee_timing_other = $auction->get->broker_fee_timing_other ?? '';

        $this->auctionId = $auctionId;
        // Initialize arrays
        $this->website_link = [''];
        $this->reviews_links = [['text' => '']];
        $this->social_media = [['platform' => '', 'text' => '']];

        // Auto-fill Agent Information from profile (Tab 6) - only for NEW bids
        $user = Auth::user();
        if (!$this->isEditMode && $user) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name = $user->last_name ?? '';
            $this->phone = $user->phone ?? '';
            $this->email = $user->email ?? '';
            $this->brokerage = $user->brokerage ?? '';
            $this->license_no = $user->license_no ?? '';
            $this->nar_id = $user->nar_id ?? '';
        }
        
        if ($this->isEditMode && $this->editBidId) {
            $existingBid = TenantAgentAuctionBidData::find($this->editBidId);
            if ($existingBid && $existingBid->user_id == Auth::id()) {
                if ($existingBid->accepted === 'accepted') {
                    session()->flash('error', 'Cannot edit an accepted bid.');
                    $this->isEditMode = false;
                    $this->editBidId = null;
                    return redirect()->route('tenant.agent.view.auction.view', $auctionId);
                }
                if ($existingBid->accepted === 'rejected') {
                    session()->flash('error', 'Cannot edit a rejected bid.');
                    $this->isEditMode = false;
                    $this->editBidId = null;
                    return redirect()->route('tenant.agent.view.auction.view', $auctionId);
                }
                
                $bidData = $existingBid->get;
                
                // Load agent info from bid data with fallback to profile
                // For edit mode, always prefer bid data over profile
                $this->first_name = (isset($bidData->first_name) && trim($bidData->first_name) !== '') 
                    ? $bidData->first_name 
                    : ($user->first_name ?? '');
                $this->last_name = (isset($bidData->last_name) && trim($bidData->last_name) !== '') 
                    ? $bidData->last_name 
                    : ($user->last_name ?? '');
                $this->phone = (isset($bidData->phone) && trim($bidData->phone) !== '') 
                    ? $bidData->phone 
                    : ($user->phone ?? '');
                $this->email = (isset($bidData->email) && trim($bidData->email) !== '') 
                    ? $bidData->email 
                    : ($user->email ?? '');
                $this->brokerage = (isset($bidData->brokerage) && trim($bidData->brokerage) !== '') 
                    ? $bidData->brokerage 
                    : ($user->brokerage ?? '');
                $this->license_no = (isset($bidData->license_no) && trim($bidData->license_no) !== '') 
                    ? $bidData->license_no 
                    : ($user->license_no ?? '');
                $this->nar_id = (isset($bidData->nar_id) && trim($bidData->nar_id) !== '') 
                    ? $bidData->nar_id 
                    : ($user->nar_id ?? '');
                
                $this->bio = $bidData->bio ?? '';
                $this->why_hire_you = $bidData->why_hire_you ?? '';
                $this->what_sets_you_apart = $bidData->what_sets_you_apart ?? '';
                $this->marketing_plan = $bidData->marketing_plan ?? '';
                $this->year_licensed = $bidData->year_licensed ?? '';
                $this->additional_details = $bidData->additional_details ?? '';
                
                $reviewsLinks = $bidData->reviews_links ?? '';
                if (is_string($reviewsLinks)) {
                    $decoded = json_decode($reviewsLinks, true);
                    $this->reviews_links = is_array($decoded) ? $decoded : [['text' => '']];
                } else {
                    $this->reviews_links = (array) $reviewsLinks ?: [['text' => '']];
                }
                
                $websiteLink = $bidData->website_link ?? '';
                $this->website_link = is_array($websiteLink) ? $websiteLink : [$websiteLink];
                
                $socialMedia = $bidData->social_media ?? '';
                if (is_string($socialMedia)) {
                    $decoded = json_decode($socialMedia, true);
                    $this->social_media = is_array($decoded) ? $decoded : [['platform' => '', 'text' => '']];
                } else {
                    $this->social_media = (array) $socialMedia ?: [['platform' => '', 'text' => '']];
                }
                
                $services = $bidData->services ?? '';
                $this->services = is_string($services) ? json_decode($services, true) ?? [] : (array) $services;
                
                $otherServices = $bidData->other_services ?? '';
                $this->other_services = is_string($otherServices) ? json_decode($otherServices, true) ?? [] : (array) $otherServices;
                $this->other_services_enabled = $bidData->other_services_enabled ?? false;
                
                $this->commission_structure = $bidData->commission_structure ?? '';
                $this->lease_fee_type = $bidData->lease_fee_type ?? '';
                $this->lease_fee_flat_type = $bidData->lease_fee_flat_type ?? '$';
                $this->lease_fee_flat = $bidData->lease_fee_flat ?? '';
                $this->lease_fee_percentage = $bidData->lease_fee_percentage ?? '';
                $this->lease_fee_percentage_monthly_rent = $bidData->lease_fee_percentage_monthly_rent ?? '';
                $this->lease_fee_percentage_monthly_number = $bidData->lease_fee_percentage_monthly_number ?? '';
                $this->lease_fee_flat_combo = $bidData->lease_fee_flat_combo ?? '';
                $this->lease_fee_percentage_combo = $bidData->lease_fee_percentage_combo ?? '';
                $this->lease_fee_percentage_net = $bidData->lease_fee_percentage_net ?? '';
                $this->lease_fee_flat_combo_net = $bidData->lease_fee_flat_combo_net ?? '';
                $this->lease_fee_percentage_combo_net = $bidData->lease_fee_percentage_combo_net ?? '';
                $this->lease_fee_other = $bidData->lease_fee_other ?? '';
                
                $this->interested_purchase_fee_type = $bidData->interested_purchase_fee_type ?? '';
                $this->purchase_fee_type = $bidData->purchase_fee_type ?? '';
                $this->purchase_fee_flat_type = $bidData->purchase_fee_flat_type ?? '$';
                $this->purchase_fee_flat = $bidData->purchase_fee_flat ?? '';
                $this->purchase_fee_percentage = $bidData->purchase_fee_percentage ?? '';
                $this->purchase_fee_percentage_combo = $bidData->purchase_fee_percentage_combo ?? '';
                $this->purchase_fee_flat_combo = $bidData->purchase_fee_flat_combo ?? '';
                $this->purchase_fee_other = $bidData->purchase_fee_other ?? '';
                
                $this->interested_lease_option_agreement = $bidData->interested_lease_option_agreement ?? '';
                $this->lease_type = $bidData->lease_type ?? 'percent';
                $this->lease_value = $bidData->lease_value ?? '';
                $this->purchase_type = $bidData->purchase_type ?? 'percent';
                $this->purchase_value = $bidData->purchase_value ?? '';
                
                $this->protection_period = $bidData->protection_period ?? '';
                $this->early_termination_fee_option = $bidData->early_termination_fee_option ?? '';
                $this->early_termination_fee_amount = $bidData->early_termination_fee_amount ?? '';
                $this->retainer_fee_option = $bidData->retainer_fee_option ?? '';
                $this->retainer_fee_amount = $bidData->retainer_fee_amount ?? '';
                $this->retainer_fee_application = $bidData->retainer_fee_application ?? '';
                $this->agency_agreement_timeframe = $bidData->agency_agreement_timeframe ?? '';
                $this->agency_agreement_custom = $bidData->agency_agreement_custom ?? '';
                $this->brokerage_relationship = $bidData->brokerage_relationship ?? '';
                $this->additional_details_broker = $bidData->additional_details_broker ?? '';
                
                // Load Broker Fee Timing fields from existing bid
                $this->broker_fee_timing = $bidData->broker_fee_timing ?? '';
                $this->broker_fee_days_from_rent = $bidData->broker_fee_days_from_rent ?? '';
                $this->broker_fee_days_after_lease = $bidData->broker_fee_days_after_lease ?? '';
                $this->broker_fee_days_after_rent = $bidData->broker_fee_days_after_rent ?? '';
                $this->broker_fee_timing_other = $bidData->broker_fee_timing_other ?? '';
                
                $this->presentation_link = $bidData->presentation_link ?? '';
                $this->business_card_link = $bidData->business_card_link ?? '';
                $this->promo_materials_link = $bidData->promo_materials_link ?? '';
                
                $this->total_marketing_fee = $bidData->total_marketing_fee ?? 0;
                $this->total_flat_fee = $bidData->total_flat_fee ?? 0;
                
                // Check both key names (legacy promo_materials and new promoMaterials)
                $promoMaterialsRaw = $bidData->promoMaterials ?? $bidData->promo_materials ?? null;
                if (!empty($promoMaterialsRaw)) {
                    if (is_string($promoMaterialsRaw)) {
                        $decoded = json_decode($promoMaterialsRaw, true);
                        if (is_array($decoded)) {
                            $this->promoMaterials = $decoded;
                        }
                    } elseif (is_array($promoMaterialsRaw)) {
                        $this->promoMaterials = $promoMaterialsRaw;
                    }
                    
                    // Normalize promoMaterials: ensure each row has all required keys
                    $this->promoMaterials = array_map(function ($m) {
                        if (is_object($m)) $m = (array) $m;
                        $m['type'] = $m['type'] ?? '';
                        $m['link'] = $m['link'] ?? '';
                        $m['other'] = $m['other'] ?? '';
                        $m['files'] = (isset($m['files']) && is_array($m['files'])) ? $m['files'] : [];
                        return $m;
                    }, $this->promoMaterials);
                }
            } else {
                $this->isEditMode = false;
                $this->editBidId = null;
            }
        }
    }

    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }

    public function debugNextClicked()
    {
        Log::info('DEBUG NEXT CLICKED', [
            'activeTab' => $this->activeTab,
            'component' => static::class,
        ]);
        session()->flash('message', 'Next button click detected - Livewire is connected!');
    }

    public function goToNextStep()
    {
        Log::info('NEXT STEP ADVANCE', ['from' => $this->activeTab]);
        
        // No validation on Next - just advance to allow navigation even if uploads fail
        // Files are validated on Submit only
        $maxTab = $this->service_type === 'full_service' ? 5 : 4;
        if ($this->activeTab < $maxTab) {
            $this->activeTab = $this->activeTab + 1;
        }
    }
    
    public function goToPreviousStep()
    {
        Log::info('PREVIOUS STEP', ['from' => $this->activeTab]);
        if ($this->activeTab > 0) {
            $this->activeTab = $this->activeTab - 1;
        }
    }

    public function addWebsiteLink()
    {
        $this->website_link[] = '';
    }

    public function removeWebsiteLink($index)
    {
        unset($this->website_link[$index]);
        $this->website_link = array_values($this->website_link);
    }





    public function submit()
    {
        DB::beginTransaction();
        try {
            $this->validate();
            
            $auction = \App\Models\TenantAgentAuction::find($this->auctionId);
            if (!$auction) {
                session()->flash('error', 'Auction not found.');
                return;
            }
            
            $endDate = strtotime($auction->end_date . ' ' . ($auction->end_time ?? '23:59:59'));
            if (time() > $endDate) {
                session()->flash('error', 'This auction has ended. Bidding is no longer available.');
                return redirect()->route('tenant.agent.auction.view', $this->auctionId);
            }
            
            if ($auction->is_sold) {
                session()->flash('error', 'This listing has been sold. Bidding is no longer available.');
                return redirect()->route('tenant.agent.auction.view', $this->auctionId);
            }

            $allowedVideos = ['mp4', 'mov', 'avi'];
            $allowedPhotos = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];

            if ($this->isEditMode && $this->editBidId) {
                $bid = TenantAgentAuctionBidData::find($this->editBidId);
                if (!$bid || $bid->user_id != Auth::id()) {
                    session()->flash('error', 'You cannot edit this bid.');
                    return;
                }
                if ($bid->accepted === 'accepted' || $bid->accepted === 'rejected') {
                    session()->flash('error', 'Cannot edit a bid that has been accepted or rejected.');
                    return;
                }
                $auction = \App\Models\TenantAgentAuction::find($bid->tenant_agent_auction_id);
                if ($auction) {
                    $endDate = strtotime($auction->end_date . ' ' . ($auction->end_time ?? '23:59:59'));
                    if (time() > $endDate) {
                        session()->flash('error', 'Cannot edit a bid after the auction has ended.');
                        return;
                    }
                }
            } else {
                $bid = new TenantAgentAuctionBidData();
                $bid->user_id = Auth::id();
                $bid->tenant_agent_auction_id = $this->auctionId;
                $bid->save();
            }



            // Save Agent Overview
            $bid->saveMeta('bio', $this->bio);
            $bid->saveMeta('why_hire_you', $this->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $this->what_sets_you_apart);
            $bid->saveMeta('marketing_plan', $this->marketing_plan);
            $bid->saveMeta('reviews_links', json_encode($this->reviews_links));
            $websiteLinkValue = is_array($this->website_link) 
                ? (count($this->website_link) > 0 ? $this->website_link[0] : '')
                : $this->website_link;
            $bid->saveMeta('website_link', $websiteLinkValue);
            $bid->saveMeta('social_media', json_encode($this->social_media));

            // Save Compensation Terms

            // Save each field
            $bid->saveMeta('commission_structure', $this->commission_structure);
            $bid->saveMeta('lease_fee_type', $this->lease_fee_type);
            $bid->saveMeta('lease_fee_flat_type', $this->lease_fee_flat_type);
            $bid->saveMeta('lease_fee_flat', $this->lease_fee_flat);
            $bid->saveMeta('lease_fee_percentage', $this->lease_fee_percentage);
            $bid->saveMeta('lease_fee_percentage_monthly_rent', $this->lease_fee_percentage_monthly_rent);
            $bid->saveMeta('lease_fee_percentage_monthly_number', $this->lease_fee_percentage_monthly_number);
            $bid->saveMeta('lease_fee_flat_combo', $this->lease_fee_flat_combo);
            $bid->saveMeta('lease_fee_percentage_combo', $this->lease_fee_percentage_combo);
            $bid->saveMeta('lease_fee_percentage_net', $this->lease_fee_percentage_net);
            $bid->saveMeta('lease_fee_flat_combo_net', $this->lease_fee_flat_combo_net);
            $bid->saveMeta('lease_fee_percentage_combo_net', $this->lease_fee_percentage_combo_net);
            $bid->saveMeta('lease_fee_other', $this->lease_fee_other);

            $bid->saveMeta('interested_purchase_fee_type', $this->interested_purchase_fee_type);
            $bid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
            $bid->saveMeta('purchase_fee_flat_type', $this->purchase_fee_flat_type);
            $bid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
            $bid->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
            $bid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
            $bid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
            $bid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

            $bid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
            $bid->saveMeta('lease_type', $this->lease_type);
            $bid->saveMeta('lease_value', $this->lease_value);
            $bid->saveMeta('purchase_type', $this->purchase_type);
            $bid->saveMeta('purchase_value', $this->purchase_value);

            $bid->saveMeta('protection_period', $this->protection_period);
            $bid->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
            $bid->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);
            $bid->saveMeta('retainer_fee_option', $this->retainer_fee_option);
            $bid->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
            $bid->saveMeta('retainer_fee_application', $this->retainer_fee_application);
            $bid->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
            $bid->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);
            $bid->saveMeta('brokerage_relationship', $this->brokerage_relationship);
            $bid->saveMeta('additional_details_broker', $this->additional_details_broker);
            $bid->saveMeta('additional_details', $this->additional_details ?? null);

            // Save Services
            $bid->saveMeta('services', json_encode($this->services));
            $bid->saveMeta('other_services', json_encode($this->other_services ?? null));
            $bid->saveMeta('other_services_enabled', $this->other_services_enabled);





            // $bid->saveMeta('custom_services', json_encode($this->custom_services));
            $bid->saveMeta('total_marketing_fee', $this->total_marketing_fee);
            $bid->saveMeta('total_flat_fee', $this->total_flat_fee);
            // Save Promotional Materials
            $bid->saveMeta('presentation_link', $this->presentation_link);
            $bid->saveMeta('business_card_link', $this->business_card_link);
            $bid->saveMeta('promo_materials_link', $this->promo_materials_link);

            // Handle video upload
            if ($this->video_upload) {
                $extension = $this->video_upload->getClientOriginalExtension();
                if (in_array($extension, $allowedVideos)) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $path = $this->video_upload->storeAs('auction/videos', $fileName, 'public');
                    $bid->saveMeta('video_upload', $path);
                }
            }

            // Handle business card upload
            if ($this->business_card) {
                $extension = $this->business_card->getClientOriginalExtension();
                if (in_array($extension, $allowedPhotos)) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $path = $this->business_card->storeAs('auction/documents', $fileName, 'public');
                    $bid->saveMeta('business_card', $path);
                }
            }

            // Handle promotional materials upload
            if ($this->promoMaterials) {
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ppt', 'pptx'];
                $toPersist = [];

                foreach ($this->promoMaterials as $entry) {
                    $rawType = trim((string)($entry['type'] ?? ''));
                    $other = trim((string)($entry['other'] ?? ''));
                    $link = trim((string)($entry['link'] ?? ''));

                    // Process files - handle both single file and array of files
                    $stored = [];
                    $files = $entry['files'] ?? [];
                    // Normalize to array if single file
                    if (!is_array($files) && is_object($files)) {
                        $files = [$files];
                    }
                    if (is_array($files) && !empty($files)) {
                        foreach ($files as $file) {
                            if (!$file) continue;
                            if (!is_object($file) || !method_exists($file, 'getClientOriginalExtension')) continue;
                            $ext = strtolower($file->getClientOriginalExtension());
                            if (!in_array($ext, $allowed, true)) continue;

                            $name = (string) Str::uuid() . '.' . $ext;
                            $path = $file->storeAs('auction/promo-materials', $name, 'public');
                            if ($path) $stored[] = $path;
                        }
                    }

                    // Only skip if ALL fields are empty
                    if ($rawType === '' && $link === '' && empty($stored)) {
                        continue;
                    }

                    $toPersist[] = [
                        'type'  => $rawType,
                        'other' => $other,
                        'link'  => $link ?: null,
                        'files' => $stored,
                    ];
                }

                $bid->saveMeta('promoMaterials', json_encode($toPersist));
            }

            // Save agent information
            $bid->saveMeta('first_name', $this->first_name);
            $bid->saveMeta('last_name', $this->last_name);
            $bid->saveMeta('phone', $this->phone);
            $bid->saveMeta('email', $this->email);
            $bid->saveMeta('brokerage', $this->brokerage);
            $bid->saveMeta('license_no', $this->license_no);
            $bid->saveMeta('year_licensed', $this->year_licensed);
            $bid->saveMeta('nar_id', $this->nar_id);

            DB::commit();
            
            $message = $this->isEditMode ? 'Your bid has been updated successfully!' : 'Your bid has been submitted successfully!';
            session()->flash('success', $message);

            return redirect()->route('tenant.agent.auction.view', $this->auctionId);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bid submission error: ' . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Error saving bid: ' . $e->getMessage());
        }
    }


    public function render()
    {
        return view('livewire.tenant.tenant-agent-auction-bid', [
            'auctionId' => $this->auctionId, // explicitly pass if you like
            'user_type' => $this->user_type, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }
}
