<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\TenantAgentAuctionBid as TenantAgentAuctionBidData;
use App\Helpers\TenantBidMatchScoreHelper;
use App\Models\AgentDefaultProfile;
use App\Services\AgentBidMapperService;
use App\Models\User;
use App\Notifications\BidSubmittedNotification;
use Illuminate\Support\Facades\DB;

class TenantAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $editBidId = null;
    public $isEditMode = false;
    public $isBiddingPeriodListing = false;
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;

    /** Agent profile_data for Build 4 / Phase 1 match scoring sub-dimensions. */
    public array $agentProfileData = [];

    public bool $defaultProfileExists = false;
    public bool $defaultProfileLoaded = false;
    public array $compatibility_agent_response = [];

    // Agent Information
    public $first_name;
    public $last_name;
    public $phone;
    public $email;
    public $brokerage;
    public $license_no;
    public $year_licensed;
    public $nar_id;

    // Availability
    public $avg_response_time = '';
    public $availability_status = '';
    public $evenings_available = '';
    public $weekends_available = '';

    // Experience & Track Record
    public $years_experience = '';
    public $transactions_last_12_months = '';
    public $is_full_time = '';
    public $primary_areas_served = '';

    // Service Areas
    public $cities_served = '';
    public $counties_served = '';
    public $neighborhoods_served = '';
    public $areas_notes = '';

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
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;
    public $brokerage_relationship;
    public $additional_details_broker;
    public $retained_deposits = '';

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
    public $business_card_stored_path = null;
    public $existingBusinessCard = null; // Stores the path of previously uploaded business card
    public $deleteExistingBusinessCard = false; // Flag to delete existing business card on save
    public $promo_material_type;
    public $promo_materials = [];
    public $promo_materials_link;
    public array $promoMaterials = [];
    public array $deletedFiles = []; // Track files to be deleted from storage on save



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
            'lease_fee_flat_combo_net',
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
            'bio'                 => 'required|string',
            'why_hire_you'        => 'required|string',
            'what_sets_you_apart' => 'required|string',
            'marketing_plan'      => 'required|string',
            'commission_structure' => 'required|string',
            'lease_fee_type' => 'required|string',
            'promoMaterials.*.type'  => ['nullable', 'string'],
            'promoMaterials.*.other' => ['nullable', 'string'],
            'promoMaterials.*.text'  => ['nullable', 'string'],
            'promoMaterials.*.files' => ['nullable', 'array'],
            // Note: File validation is handled manually in submit() to allow
            // existing file paths (strings) in edit mode while validating new uploads
            'year_licensed' => 'required|numeric|min:1900|max:' . date('Y'),
            'first_name'  => 'required|string',
            'last_name'   => 'required|string',
            'phone'       => 'required|string',
            'email'       => 'required|email',
            'brokerage'   => 'required|string',
            'license_no'  => 'required|string',
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected $messages = [
        'bio.required'                 => 'Please fill in "About Agent".',
        'why_hire_you.required'        => 'Please fill in "Why Should You Be Hired as Their Agent?".',
        'what_sets_you_apart.required' => 'Please fill in "What Sets You Apart From Other Agents?".',
        'marketing_plan.required'      => 'Please fill in "What Is Your Marketing Strategy?".',
        'commission_structure.required' => 'Please select a Tenant\'s Broker Commission Structure.',
        'lease_fee_type.required' => 'Please select a Tenant\'s Broker Lease Fee.',
        'year_licensed.required'       => 'Please enter the year you were licensed.',
        'first_name.required'  => 'Please enter your first name.',
        'last_name.required'   => 'Please enter your last name.',
        'phone.required'       => 'Please enter your phone number.',
        'email.required'       => 'Please enter your email address.',
        'email.email'          => 'Please enter a valid email address.',
        'brokerage.required'   => 'Please enter your brokerage name.',
        'license_no.required'  => 'Please enter your real estate license number.',
        'referral_fee_percent.numeric'  => 'Referral fee must be a number.',
        'referral_fee_percent.between'  => 'Referral fee must be between 0 and 100.',
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



    public function syncInput($name, $value, $rehash = true)
    {
        // Guard against empty/null property name to prevent Str::studly() crash
        if (empty($name) || $name === null) {
            return;
        }
        
        return parent::syncInput($name, $value, $rehash);
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

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
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
            // Queue existing files for deletion from storage
            $files = $this->promoMaterials[$index]['files'] ?? [];
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_string($file) && !empty($file)) {
                        $this->deletedFiles[] = $file;
                    }
                }
            }
            
            array_splice($this->promoMaterials, $index, 1);
            $this->promoMaterials = array_values($this->promoMaterials);
        }
    }

    public function removeExistingFile(int $materialIndex, int $fileIndex): void
    {
        if (isset($this->promoMaterials[$materialIndex]['files'][$fileIndex])) {
            $file = $this->promoMaterials[$materialIndex]['files'][$fileIndex];
            
            // Track existing file paths (strings) for deletion from storage on save
            if (is_string($file) && !empty($file)) {
                $this->deletedFiles[] = $file;
            }
            
            // Remove the file from the array
            array_splice($this->promoMaterials[$materialIndex]['files'], $fileIndex, 1);
            // Re-index the array
            $this->promoMaterials[$materialIndex]['files'] = array_values($this->promoMaterials[$materialIndex]['files']);
        }
    }

    public function clearFileError(int $index): void
    {
        $this->resetErrorBag("promoMaterials.{$index}.files");
        $this->resetErrorBag("promoMaterials.{$index}.files.*");
    }

    public function removeExistingBusinessCard(): void
    {
        $this->deleteExistingBusinessCard = true;
        $this->existingBusinessCard = null;
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
    private function normalizeTenantServiceLabels(array $services): array
    {
        return array_map(function ($s) {
            return str_replace("\x27", "\u{2019}", str_replace("\x5c\x27", "\u{2019}", $s));
        }, $services);
    }

    /**
     * Remove any services that do not belong to the current property-type's
     * Tenant agent catalog. This guards against cross-role contamination (e.g.
     * Buyer or Seller services that were carried in from a default profile).
     * The original string form (encoding) of each valid service is preserved.
     */
    private function filterServicesToCurrentCatalog(array $services): array
    {
        $propType = $this->property_type ?: '';
        if ($propType === '') {
            return $services;
        }

        $catalog = TenantBidMatchScoreHelper::getCatalog($propType);
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

        $this->compatibility_agent_response = [
            'communication_preferences'  => [],
            'negotiation_approach'       => [],
            'guidance_style'             => [],
            'collaboration_preferences'  => [],
            'transaction_strategy'       => [],
            'representation_philosophy'  => [],
            'representation_priorities'  => [],
        ];

        $auction = \App\Models\TenantAgentAuction::find($auctionId);
        
        if (!$auction) {
            session()->flash('error', 'Auction not found.');
            return redirect()->route('home');
        }
        
        $this->isBiddingPeriodListing = $auction->isBiddingPeriodType();
        
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

        $rawServices = $auction->get->services ?? null;
        $this->services = $this->normalizeTenantServiceLabels(
            is_string($rawServices) ? (json_decode($rawServices, true) ?? []) : (is_array($rawServices) ? $rawServices : [])
        );
        $rawOtherServices = $auction->get->other_services ?? null;
        $this->other_services = is_string($rawOtherServices) ? (json_decode($rawOtherServices, true) ?? []) : (is_array($rawOtherServices) ? $rawOtherServices : []);

        $this->other_services_enabled = (bool)($auction->get->other_services_enabled ?? false);
        $this->service_type = $auction->get->service_type ?? '';
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
        $this->referral_fee_percent = $auction->get->referral_percentage ?? '';
        $this->isListingCreatedByAgent = $auction->isCreatedByAgent();

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

            // Auto-load Default Profile for new bids (Agent Overview fields)
            $profile = AgentDefaultProfile::findForAgentWithFallback(
                $user->id,
                'tenant',
                $this->property_type ?: 'residential'
            );
            $mapped = $profile ? AgentBidMapperService::mapFromProfile($profile->profile_data ?? []) : null;
            if ($mapped !== null) {
                $this->defaultProfileExists  = true;
                $presetFieldsApplied          = 0;
                $this->applyPresetField('bio', $mapped['bio'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('why_hire_you', $mapped['why_hire_you'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('what_sets_you_apart', $mapped['what_sets_you_apart'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('marketing_plan', $mapped['marketing_plan'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('year_licensed', $mapped['year_licensed'] ?? null, $presetFieldsApplied);
                if (!empty($mapped['reviews_links']))             $this->reviews_links             = $mapped['reviews_links'];
                if (!empty($mapped['website_link']))              $this->website_link              = $mapped['website_link'];
                if (!empty($mapped['social_media']))              $this->social_media              = $mapped['social_media'];
                $this->applyPresetField('additional_details', $mapped['additional_details'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('first_name', $mapped['first_name'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('last_name', $mapped['last_name'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('phone', $mapped['phone'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('email', $mapped['email'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('brokerage', $mapped['brokerage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('license_no', $mapped['license_no'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('nar_id', $mapped['nar_id'] ?? null, $presetFieldsApplied);
                // Availability
                $this->applyPresetField('avg_response_time',   $mapped['avg_response_time']   ?? null, $presetFieldsApplied);
                $this->applyPresetField('availability_status', $mapped['availability_status'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('evenings_available',  $mapped['evenings_available']  ?? null, $presetFieldsApplied);
                $this->applyPresetField('weekends_available',  $mapped['weekends_available']  ?? null, $presetFieldsApplied);
                // Experience & Track Record
                $this->applyPresetField('years_experience',           $mapped['years_experience']           ?? null, $presetFieldsApplied);
                $this->applyPresetField('transactions_last_12_months', $mapped['transactions_last_12_months'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('is_full_time',               $mapped['is_full_time']               ?? null, $presetFieldsApplied);
                $this->applyPresetField('primary_areas_served',       $mapped['primary_areas_served']       ?? null, $presetFieldsApplied);
                // Service Areas
                $this->applyPresetField('cities_served',        $mapped['cities_served']        ?? null, $presetFieldsApplied);
                $this->applyPresetField('counties_served',      $mapped['counties_served']      ?? null, $presetFieldsApplied);
                $this->applyPresetField('neighborhoods_served', $mapped['neighborhoods_served'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('areas_notes',          $mapped['areas_notes']          ?? null, $presetFieldsApplied);
                $this->applyPresetField('presentation_link', $mapped['presentation_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_link', $mapped['business_card_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_stored_path', $mapped['business_card_stored_path'] ?? null, $presetFieldsApplied);
                if (!empty($mapped['promoMaterials']))            $this->promoMaterials            = $mapped['promoMaterials'];
                if (!empty($mapped['services'])) {
                    $filtered = $this->filterServicesToCurrentCatalog($mapped['services']);
                    if (!empty($filtered)) {
                        $this->services = $this->normalizeTenantServiceLabels($filtered);
                    }
                }
                if (!empty($mapped['other_services']))            $this->other_services            = $mapped['other_services'];
                // Broker Compensation fields from preset
                $this->applyPresetField('commission_structure', $mapped['commission_structure'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_type', $mapped['lease_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_flat', $mapped['lease_fee_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage', $mapped['lease_fee_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage_monthly_rent', $mapped['lease_fee_percentage_monthly_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage_monthly_number', $mapped['lease_fee_percentage_monthly_number'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_flat_combo', $mapped['lease_fee_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage_combo', $mapped['lease_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage_net', $mapped['lease_fee_percentage_net'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_flat_combo_net', $mapped['lease_fee_flat_combo_net'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_percentage_combo_net', $mapped['lease_fee_percentage_combo_net'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_fee_other', $mapped['lease_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_purchase_fee_type', $mapped['interested_purchase_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_type', $mapped['purchase_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat', $mapped['purchase_fee_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_percentage', $mapped['purchase_fee_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_percentage_combo', $mapped['purchase_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat_combo', $mapped['purchase_fee_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_other', $mapped['purchase_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_lease_option_agreement', $mapped['interested_lease_option_agreement'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_type', $mapped['lease_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('lease_value', $mapped['lease_value'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_type', $mapped['purchase_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_value', $mapped['purchase_value'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('protection_period', $mapped['protection_period'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('early_termination_fee_option', $mapped['early_termination_fee_option'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('early_termination_fee_amount', $mapped['early_termination_fee_amount'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('retainer_fee_option', $mapped['retainer_fee_option'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('retainer_fee_amount', $mapped['retainer_fee_amount'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('retainer_fee_application', $mapped['retainer_fee_application'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_timing', $mapped['broker_fee_timing'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_from_rent', $mapped['broker_fee_days_from_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_after_lease', $mapped['broker_fee_days_after_lease'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_after_rent', $mapped['broker_fee_days_after_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_timing_other', $mapped['broker_fee_timing_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('brokerage_relationship', $mapped['brokerage_relationship'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('additional_details_broker', $mapped['additional_details_broker'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('retained_deposits', $mapped['retained_deposits'] ?? null, $presetFieldsApplied);
                // Compatibility preferences from preset — blank-field guard:
                // only fill a section if the current bid has no data for that section yet.
                $compatFromPreset = AgentBidMapperService::mapCompatibilityFromProfile($profile->profile_data ?? []);
                foreach ($compatFromPreset as $_cpSection => $_cpData) {
                    if (is_array($_cpData) && !empty($_cpData)) {
                        $existing = $this->compatibility_agent_response[$_cpSection] ?? null;
                        if (empty($existing)) {
                            $this->compatibility_agent_response[$_cpSection] = $_cpData;
                        }
                    }
                }
                if ($presetFieldsApplied > 0) {
                    $this->defaultProfileLoaded = true;
                    try {
                        DB::table('agent_preset_events')->insert([
                            'user_id'               => Auth::id(),
                            'role'                  => 'tenant',
                            'property_type'         => $this->property_type,
                            'preset_id'             => $profile->id,
                            'listing_id'            => $this->auctionId,
                            'event'                 => 'preset_applied',
                            'field_count_populated' => $presetFieldsApplied,
                            'created_at'            => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('preset_applied analytics failed', ['error' => $e->getMessage()]);
                    }
                }
            }
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
                $this->avg_response_time          = $bidData->avg_response_time          ?? '';
                $this->availability_status        = $bidData->availability_status        ?? '';
                $this->evenings_available         = $bidData->evenings_available         ?? '';
                $this->weekends_available         = $bidData->weekends_available         ?? '';
                $this->years_experience           = $bidData->years_experience           ?? '';
                $this->transactions_last_12_months = $bidData->transactions_last_12_months ?? '';
                $this->is_full_time               = $bidData->is_full_time               ?? '';
                $this->primary_areas_served       = $bidData->primary_areas_served       ?? '';
                $this->cities_served              = $bidData->cities_served              ?? '';
                $this->counties_served            = $bidData->counties_served            ?? '';
                $this->neighborhoods_served       = $bidData->neighborhoods_served       ?? '';
                $this->areas_notes                = $bidData->areas_notes                ?? '';
                
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
                } elseif (is_array($reviewsLinks) || is_object($reviewsLinks)) {
                    $rl = (array) $reviewsLinks;
                    $this->reviews_links = !empty($rl)
                        ? array_map(fn($r) => is_object($r) ? (array) $r : (is_array($r) ? $r : ['text' => '']), $rl)
                        : [['text' => '']];
                } else {
                    $this->reviews_links = [['text' => '']];
                }
                
                $websiteLink = $bidData->website_link ?? '';
                $this->website_link = is_array($websiteLink) ? $websiteLink : [$websiteLink];
                
                $socialMedia = $bidData->social_media ?? '';
                if (is_string($socialMedia)) {
                    $decoded = json_decode($socialMedia, true);
                    $this->social_media = is_array($decoded) ? $decoded : [['platform' => '', 'text' => '']];
                } elseif (is_array($socialMedia) || is_object($socialMedia)) {
                    $sm = (array) $socialMedia;
                    $this->social_media = !empty($sm)
                        ? array_map(fn($m) => is_object($m) ? (array) $m : (is_array($m) ? $m : ['platform' => '', 'text' => '']), $sm)
                        : [['platform' => '', 'text' => '']];
                } else {
                    $this->social_media = [['platform' => '', 'text' => '']];
                }
                
                $services = $bidData->services ?? '';
                $this->services = $this->normalizeTenantServiceLabels(
                    is_string($services) ? json_decode($services, true) ?? [] : (array) $services
                );
                
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
                $this->referral_fee_percent = $bidData->referral_fee_percent ?? '';

                // Load Broker Fee Timing fields from existing bid
                $this->broker_fee_timing = $bidData->broker_fee_timing ?? '';
                $this->broker_fee_days_from_rent = $bidData->broker_fee_days_from_rent ?? '';
                $this->broker_fee_days_after_lease = $bidData->broker_fee_days_after_lease ?? '';
                $this->broker_fee_days_after_rent = $bidData->broker_fee_days_after_rent ?? '';
                $this->broker_fee_timing_other = $bidData->broker_fee_timing_other ?? '';
                
                $this->presentation_link = $bidData->presentation_link ?? '';
                $this->business_card_link = $bidData->business_card_link ?? '';
                $this->existingBusinessCard = $bidData->business_card ?? null;
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

                // Compatibility preferences from saved bid
                $this->compatibility_agent_response = array_merge(
                    $this->compatibility_agent_response,
                    $existingBid->loadCompatibilityPreferences()
                );
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

    public function hasReferralTab(): bool
    {
        return $this->isListingCreatedByAgent ?? false;
    }

    public function goToNextStep()
    {
        Log::info('NEXT STEP ADVANCE', ['from' => $this->activeTab]);

        // Validate Agent Overview required fields when leaving Tab 0
        if ($this->activeTab === 0) {
            $this->validateOnly('bio', ['bio' => 'required|string'], $this->messages);
            $this->validateOnly('why_hire_you', ['why_hire_you' => 'required|string'], $this->messages);
            $this->validateOnly('what_sets_you_apart', ['what_sets_you_apart' => 'required|string'], $this->messages);
            $this->validateOnly('marketing_plan', ['marketing_plan' => 'required|string'], $this->messages);
            $this->validateOnly('year_licensed', ['year_licensed' => 'required|numeric|min:1900|max:' . date('Y')], $this->messages);

            if ($this->getErrorBag()->hasAny(['bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan', 'year_licensed'])) {
                return;
            }
        }

        $maxTab = $this->service_type === 'full_service' ? ($this->hasReferralTab() ? 7 : 6) : 5;
        if ($this->activeTab < $maxTab) {
            $this->activeTab = $this->activeTab + 1;
        }
    }

    private function storeProfileFiles(): void
    {
        foreach ($this->promoMaterials as $i => $m) {
            $files = $m['files'] ?? [];
            if (!is_array($files)) $files = (is_object($files) ? [$files] : []);
            $storedPaths = [];
            foreach ($files as $file) {
                if (is_string($file) && !empty($file)) {
                    $storedPaths[] = $file;
                } elseif (is_object($file) && method_exists($file, 'store')) {
                    $path = $file->store('auction/promo-materials', 'public');
                    if ($path) $storedPaths[] = $path;
                }
            }
            $this->promoMaterials[$i]['files'] = $storedPaths;
        }
        if ($this->business_card && is_object($this->business_card) && method_exists($this->business_card, 'store')) {
            $path = $this->business_card->store('auction/documents', 'public');
            if ($path) $this->business_card_stored_path = $path;
        }
    }

    private function buildProfileData(): array
    {
        return [
            'bio'                       => $this->bio,
            'why_hire_you'              => $this->why_hire_you,
            'what_sets_you_apart'       => $this->what_sets_you_apart,
            'marketing_plan'            => $this->marketing_plan,
            'reviews_links'             => $this->reviews_links,
            'website_link'              => $this->website_link,
            'social_media'              => $this->social_media,
            'additional_details'        => $this->additional_details,
            'year_licensed'             => $this->year_licensed,
            'first_name'                => $this->first_name,
            'last_name'                 => $this->last_name,
            'phone'                     => $this->phone,
            'email'                     => $this->email,
            'brokerage'                 => $this->brokerage,
            'license_no'                => $this->license_no,
            'nar_id'                    => $this->nar_id,
            'avg_response_time'           => $this->avg_response_time,
            'availability_status'         => $this->availability_status,
            'evenings_available'          => $this->evenings_available,
            'weekends_available'          => $this->weekends_available,
            'years_experience'            => $this->years_experience,
            'transactions_last_12_months' => $this->transactions_last_12_months,
            'is_full_time'                => $this->is_full_time,
            'primary_areas_served'        => $this->primary_areas_served,
            'cities_served'               => $this->cities_served,
            'counties_served'             => $this->counties_served,
            'neighborhoods_served'        => $this->neighborhoods_served,
            'areas_notes'                 => $this->areas_notes,
            'presentation_link'         => $this->presentation_link,
            'business_card_link'        => $this->business_card_link,
            'business_card_stored_path' => $this->business_card_stored_path,
            'promoMaterials'            => array_map(fn($m) => [
                'type'  => $m['type']  ?? '',
                'other' => $m['other'] ?? '',
                'text'  => $m['text']  ?? ($m['link'] ?? ''),
                'link'  => $m['link']  ?? ($m['text'] ?? ''),
                'files' => array_values(array_filter(
                    array_map(fn($f) => is_string($f) ? $f : null, is_array($m['files'] ?? null) ? $m['files'] : [])
                )),
            ], $this->promoMaterials),
        ];
    }

    public function saveAsDefaultProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $this->storeProfileFiles();
        $propType = $this->property_type ?: 'residential';
        $data = $this->buildProfileData();

        AgentDefaultProfile::upsertForAgent($user->id, 'tenant', $propType, $data);

        if (!AgentDefaultProfile::findRoleDefault($user->id, 'tenant')) {
            AgentDefaultProfile::upsertRoleDefault($user->id, 'tenant', $data);
        }

        $this->defaultProfileExists = true;
        $this->defaultProfileLoaded = true;
        session()->flash('success', 'Default profile saved for ' . ucfirst($propType) . '. It will pre-fill your bid next time.');
    }

    public function saveAsRoleDefault(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $this->storeProfileFiles();
        AgentDefaultProfile::upsertRoleDefault($user->id, 'tenant', $this->buildProfileData());

        $this->defaultProfileExists = true;
        $this->defaultProfileLoaded = true;
        session()->flash('success', 'Saved as your main Tenant default profile — it will pre-fill all property types that don\'t have their own override.');
    }

    public function loadDefaultProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $profile = AgentDefaultProfile::findForAgentWithFallback($user->id, 'tenant', $this->property_type ?: 'residential');
        if (!$profile) return;

        $data = $profile->profile_data ?? [];
        $this->bio                 = $data['bio'] ?? '';
        $this->why_hire_you        = $data['why_hire_you'] ?? '';
        $this->what_sets_you_apart = $data['what_sets_you_apart'] ?? '';
        $this->marketing_plan      = $data['marketing_plan'] ?? '';
        $this->reviews_links       = $data['reviews_links'] ?? [['url' => '', 'text' => '']];
        $this->website_link        = $data['website_link'] ?? '';
        $this->social_media        = $data['social_media'] ?? [['platform' => '', 'url' => '']];
        $this->additional_details  = $data['additional_details'] ?? '';
        $this->year_licensed       = $data['year_licensed'] ?? '';
        if (!empty($data['first_name']))        $this->first_name        = $data['first_name'];
        if (!empty($data['last_name']))         $this->last_name         = $data['last_name'];
        if (!empty($data['phone']))             $this->phone             = $data['phone'];
        if (!empty($data['email']))             $this->email             = $data['email'];
        if (!empty($data['brokerage']))         $this->brokerage         = $data['brokerage'];
        if (!empty($data['license_no']))        $this->license_no        = $data['license_no'];
        if (!empty($data['nar_id']))            $this->nar_id            = $data['nar_id'];
        $this->avg_response_time          = $data['avg_response_time']          ?? '';
        $this->availability_status        = $data['availability_status']        ?? '';
        $this->evenings_available         = $data['evenings_available']         ?? '';
        $this->weekends_available         = $data['weekends_available']         ?? '';
        $this->years_experience           = $data['years_experience']           ?? '';
        $this->transactions_last_12_months = $data['transactions_last_12_months'] ?? '';
        $this->is_full_time               = $data['is_full_time']               ?? '';
        $this->primary_areas_served       = $data['primary_areas_served']       ?? '';
        $this->cities_served              = $data['cities_served']              ?? '';
        $this->counties_served            = $data['counties_served']            ?? '';
        $this->neighborhoods_served       = $data['neighborhoods_served']       ?? '';
        $this->areas_notes                = $data['areas_notes']                ?? '';
        if (!empty($data['presentation_link']))         $this->presentation_link         = $data['presentation_link'];
        if (!empty($data['business_card_link']))         $this->business_card_link         = $data['business_card_link'];
        if (!empty($data['business_card_stored_path'])) $this->business_card_stored_path  = $data['business_card_stored_path'];
        if (!empty($data['promoMaterials']))             $this->promoMaterials             = $data['promoMaterials'];
        // Compatibility preferences from profile
        $compatData = AgentBidMapperService::mapCompatibilityFromProfile($data);
        foreach ($compatData as $_cpSection => $_cpData) {
            if (is_array($_cpData) && !empty($_cpData)) {
                $this->compatibility_agent_response[$_cpSection] = $_cpData;
            }
        }
        $this->defaultProfileLoaded = true;
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
        Log::info('[TenantBid] submit() called', ['auctionId' => $this->auctionId, 'userId' => Auth::id()]);
        DB::beginTransaction();
        try {
            $this->validate();
            Log::info('[TenantBid] validation passed');
            
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

            $bid->saveMeta('broker_fee_timing', $this->broker_fee_timing);
            $bid->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
            $bid->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
            $bid->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
            $bid->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);

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
            $bid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
            $bid->saveMeta('purchase_type', $this->purchase_type);
            $bid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

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
            $bid->saveMeta('retained_deposits', $this->retained_deposits);
            if ($auction->isCreatedByAgent()) {
                $bid->saveMeta('referral_fee_percent', $this->referral_fee_percent);
            }
            $bid->saveMeta('additional_details', $this->additional_details ?? null);

            // Save Services — filter to Tenant catalog first to prevent cross-role
            // contamination (e.g. Buyer services from a shared default profile).
            $this->services = $this->filterServicesToCurrentCatalog($this->services);
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

            // Handle business card upload or deletion
            if ($this->business_card) {
                // New upload - delete old file if exists and upload new one
                $extension = $this->business_card->getClientOriginalExtension();
                if (in_array($extension, $allowedPhotos)) {
                    // Delete old business card if there was one
                    if ($this->isEditMode && $this->editBidId) {
                        $existingBid = TenantAgentAuctionBidData::find($this->editBidId);
                        $oldPath = $existingBid ? $existingBid->info('business_card') : null;
                        if ($oldPath && is_string($oldPath) && strpos($oldPath, 'auction/documents/') === 0) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                    
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $path = $this->business_card->storeAs('auction/documents', $fileName, 'public');
                    $bid->saveMeta('business_card', $path);
                }
            } elseif ($this->deleteExistingBusinessCard && $this->isEditMode && $this->editBidId) {
                // User explicitly deleted the existing business card
                $existingBid = TenantAgentAuctionBidData::find($this->editBidId);
                $oldPath = $existingBid ? $existingBid->info('business_card') : null;
                if ($oldPath && is_string($oldPath) && strpos($oldPath, 'auction/documents/') === 0) {
                    Storage::disk('public')->delete($oldPath);
                }
                $bid->saveMeta('business_card', null);
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
                            
                            // Preserve existing file paths (strings) from previous uploads
                            if (is_string($file) && !empty($file)) {
                                $stored[] = $file;
                                continue;
                            }
                            
                            // Process new file uploads (objects)
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

            // Delete files that were marked for removal from storage
            // Security: Only delete files that belong to this bid and are in the promo-materials directory
            if (!empty($this->deletedFiles) && $this->isEditMode && $this->editBidId) {
                $allowedPrefix = 'auction/promo-materials/';
                
                // Get the bid's original promo materials to verify ownership
                $originalBid = TenantAgentAuctionBidData::find($this->editBidId);
                $originalPromoRaw = $originalBid ? $originalBid->info('promoMaterials') : null;
                $ownedFiles = [];
                
                if ($originalPromoRaw) {
                    $originalPromo = is_string($originalPromoRaw) ? json_decode($originalPromoRaw, true) : $originalPromoRaw;
                    if (is_array($originalPromo)) {
                        foreach ($originalPromo as $material) {
                            $files = $material['files'] ?? [];
                            if (is_array($files)) {
                                foreach ($files as $file) {
                                    if (is_string($file) && !empty($file)) {
                                        $ownedFiles[] = $file;
                                    }
                                }
                            }
                        }
                    }
                }
                
                foreach ($this->deletedFiles as $filePath) {
                    if (is_string($filePath) && !empty($filePath)) {
                        // Validate: must be in allowed directory, no path traversal, AND owned by this bid
                        $isInAllowedDir = strpos($filePath, $allowedPrefix) === 0;
                        $hasNoTraversal = strpos($filePath, '..') === false;
                        $isOwned = in_array($filePath, $ownedFiles, true);
                        
                        if ($isInAllowedDir && $hasNoTraversal && $isOwned) {
                            Storage::disk('public')->delete($filePath);
                        } else {
                            Log::warning('Blocked unauthorized file deletion attempt', [
                                'path' => $filePath, 
                                'bid_id' => $this->editBidId,
                                'reason' => !$isInAllowedDir ? 'wrong_directory' : (!$hasNoTraversal ? 'path_traversal' : 'not_owned')
                            ]);
                        }
                    }
                }
                // Reset the deleted files array
                $this->deletedFiles = [];
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
            // Availability, Experience & Service Areas
            $bid->saveMeta('avg_response_time',          $this->avg_response_time);
            $bid->saveMeta('availability_status',        $this->availability_status);
            $bid->saveMeta('evenings_available',         $this->evenings_available);
            $bid->saveMeta('weekends_available',         $this->weekends_available);
            $bid->saveMeta('years_experience',           $this->years_experience);
            $bid->saveMeta('transactions_last_12_months', $this->transactions_last_12_months);
            $bid->saveMeta('is_full_time',               $this->is_full_time);
            $bid->saveMeta('primary_areas_served',       $this->primary_areas_served);
            $bid->saveMeta('cities_served',              $this->cities_served);
            $bid->saveMeta('counties_served',            $this->counties_served);
            $bid->saveMeta('neighborhoods_served',       $this->neighborhoods_served);
            $bid->saveMeta('areas_notes',                $this->areas_notes);

            $bid->saveCompatibilityPreferences($this->compatibility_agent_response);

            DB::commit();
            
            // Send notification to listing owner
            try {
                $listingOwner = User::find($auction->user_id);
                if ($listingOwner) {
                    if ($this->isEditMode) {
                        // Notify tenant that agent modified their bid
                        $listingOwner->notify(new \App\Notifications\BidModifiedNotification($bid, $auction));
                    } else {
                        // Notify tenant of new bid
                        $listingOwner->notify(new BidSubmittedNotification($bid, $auction));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid notification', [
                    'bid_id' => $bid->id,
                    'auction_id' => $auction->id,
                    'is_edit' => $this->isEditMode,
                    'error' => $e->getMessage()
                ]);
            }
            
            $message = $this->isEditMode ? 'Your bid has been updated successfully!' : 'Your bid has been submitted successfully!';
            session()->flash('success', $message);

            return redirect()->route('tenant.agent.auction.view', $this->auctionId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::warning('[TenantBid] ValidationException caught', ['errors' => $e->errors()]);
            $this->activeTab = 0;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[TenantBid] Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Error saving bid: ' . $e->getMessage());
        }
    }



    /**
     * Write a scalar preset value to a component property only when:
     *   (a) the preset value is non-empty, AND
     *   (b) the component property is currently blank (blank-field protection —
     *       never overwrites listing-prefilled or agent-entered values).
     * Increments $count for every field actually written (used for analytics).
     */
    private function applyPresetField(string $field, mixed $value, int &$count): void
    {
        if (!empty($value) && trim((string)($this->$field ?? '')) === '') {
            $this->$field = $value;
            $count++;
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
