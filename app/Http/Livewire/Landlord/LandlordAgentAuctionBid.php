<?php

namespace App\Http\Livewire\Landlord;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\LandlordAgentAuctionBid as LandlordAgentAuctionBidData;
use App\Models\AgentDefaultProfile;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\BidSubmitted;
use App\Notifications\BidSubmittedNotification;

class LandlordAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;

    public bool $defaultProfileExists = false;
    public bool $defaultProfileLoaded = false;

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
    public $social_media = [['platform' => '', 'url' => '']];




    // Additional Details
    public $additional_details;

    // Tenant Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = []; // Always initialize as an array



    // For Lease Option Agreement
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent'; // 'percent' or 'flat'
    public $lease_value = '';
    public $purchase_type = 'percent'; // 'percent' or 'flat'
    public $purchase_value = '';

    // For Residential Property Lease Fee
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_rental_period = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';

    // For Commercial Property Lease Fee
    public $purchase_fee_net_aggregate = '';
    public $purchase_fee_gross_rent = '';
    public $sales_tax_option_gross = '';
    public $purchase_fee_monthly_percentage = '';
    public $purchase_fee_months = '';
    public $sales_tax_option_monthly = '';
    public $purchase_fee_flat_commercial = '';
    public $sales_tax_option_flat = '';
    public $purchase_fee_purchase_price = '';
    public $purchase_fee_other_commercial = '';

    // For Interested in Selling
    public $interested_in_selling = '';
    public $interested_in_selling_type = '';
    public $landlord_broker_purchase_price = '';
    public $landlord_broker_percentage_price = '';
    public $landlord_broker_dollar_price = '';
    public $landlord_broker_flate_fee = '';
    public $landlord_broker_other = '';

    // For Payment Timing
    public $broker_fee_timing = '';
    public $broker_fee_days_from_rent = '';
    public $broker_fee_days_after_lease = '';
    public $broker_fee_days_after_rent = '';
    public $broker_fee_timing_other = '';
    public $split_payment_due = '';
    public $split_payment_due_other = '';
    public $broker_fee_days_after_due_event = '';

    // For Renewal/Extension Fees
    public $renewal_fee_type = '';
    public $renewal_fee_percentage = '';
    public $renewal_fee_lease_value = '';
    public $renewal_fee_first_month = '';
    public $renewal_fee_flat_free = '';
    public $renewal_fee_custom = '';
    public $renewal_fee_sales_tax_lease_value = '';
    public $renewal_fee_no_of_months = '';
    public $renewal_fee_sales_tax_first_month = '';
    public $renewal_fee_sales_tax_flat_fee = '';

    // For Commercial Expansion Commission
    public $expansion_commission_percentage = '';

    // For Tenant's Broker Commission (Residential)
    public $tenant_broker_commission_structure = '';
    public $tenant_broker_fee_structure = '';
    public $tenant_broker_percentage = '';
    public $tenant_broker_gross_lease = '';
    public $tenant_broker_first_month_rent = '';
    public $tenant_broker_flat_fee = '';
    public $tenant_broker_other = '';

    // Other important properties
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $interested_in_property_management = '';
    public $interested_in_property_management_fee = '';
    public $interested_in_property_management_fee_gross_lease = '';
    public $interested_in_property_management_fee_rental_periord = '';
    public $interested_in_property_management_fee_flate_free = '';
    public $interested_in_property_management_fee_other = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';


    // Presentation & Promotional Materials
    public $presentation_link;
    public $video_upload;
    public $business_card_link;
    public $business_card;
    public $business_card_stored_path = null;
    public $promo_material_type;
    public $promo_materials = [];
    public $promo_materials_link;

    public array $promoMaterials = [];
    // Broker
    public $commission_structure = '';

    public $showEnhancements = false;
    public $showCustomEnhancement = false;
    public $purchase_fee_flat_type = '$';   // $ | %
    public string $lease_fee_flat_type = '$';
    public $lease_fee_flat = '';
    public $photo_enhancements = [];
    public $custom_enhancement = '';
    public $embedUrl;
    // used as suffix chooser in Flat Fee

    protected function rules(): array
    {
        return [
            'bio'                 => 'required|string',
            'why_hire_you'        => 'required|string',
            'what_sets_you_apart' => 'required|string',
            'marketing_plan'      => 'required|string',
            'promoMaterials.*.type'  => ['nullable', 'string'],
            'promoMaterials.*.other' => ['nullable', 'string'],
            'promoMaterials.*.link'  => ['nullable', 'url'],
            'promoMaterials.*.files' => ['nullable', 'array'],
            'year_licensed' => 'required|numeric|min:1900|max:' . date('Y'),

        ];
    }

    protected array $messages = [
        'bio.required'                 => 'Please fill in "About Agent".',
        'why_hire_you.required'        => 'Please fill in "Why Should You Be Hired as Their Agent?".',
        'what_sets_you_apart.required' => 'Please fill in "What Sets You Apart From Other Agents?".',
        'marketing_plan.required'      => 'Please fill in "What Is Your Marketing Strategy?".',
        'year_licensed.required'       => 'Please enter the year you were licensed.',
    ];

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

    public function updatedVideoLink($value)
    {
        // instantly preview when pasted or typed
        $this->embedUrl = $this->getEmbedUrl($value);
    }

    public function previewVideo()
    {
        $this->embedUrl = $this->getEmbedUrl($this->presentation_link);
    }
    public function getEmbedUrl($url)
    {
        // ✅ YouTube
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/i', $url, $m);
            $videoId = $m[1] ?? null;
            return $videoId
                ? "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&playsinline=1"
                : null;
        }

        // ✅ Vimeo (handles: /123, /channels/.../123, /groups/.../videos/123, /album/.../video/123)
        if (str_contains($url, 'vimeo.com')) {
            // Grab the last numeric segment anywhere in the path
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)(?:[\/?#]|$)/i', $url, $m);
            $videoId = $m[1] ?? null;

            // Also handle player links like player.vimeo.com/video/123
            if (!$videoId && str_contains($url, 'player.vimeo.com')) {
                preg_match('/player\.vimeo\.com\/video\/(\d+)/i', $url, $m2);
                $videoId = $m2[1] ?? null;
            }

            return $videoId
                ? "https://player.vimeo.com/video/{$videoId}?autoplay=1&muted=1&playsinline=1"
                : null;
        }

        return null;
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
        $this->social_media[] = ['platform' => '', 'url' => '', 'placeholder' => 'Enter profile link'];
    }

    public function removeSocialMedia($index)
    {
        unset($this->social_media[$index]);
        $this->social_media = array_values($this->social_media); // Re-index array after removal
    }

    /**
     * Override callUpdatedHook to guard against empty property names before Str::studly() is called.
     * This prevents crashes when wire:model.lazy sends empty property names during select transitions.
     */
    protected function callUpdatedHook($name, $value = null)
    {
        if (blank($name)) {
            return;
        }
        return parent::callUpdatedHook($name, $value);
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
        $this->reviews_links[] = ['url' => '']; // Adds a new blank input field
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

        $this->promoMaterials = [
            [
                'type'  => '',
                'other' => '',
                'link'  => '',
                'files' => [],
            ],
        ];


        $auction = \App\Models\LandlordAgentAuction::find($auctionId);
        // $this->additional_details = $auction->get->additional_details ?? '';

        $this->services = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;
        $this->other_services = is_string($auction->get->other_services) ? json_decode($auction->get->other_services, true) ?? [] : (array)$auction->get->other_services;

        $this->other_services_enabled = (bool)($auction->get->other_services_enabled ?? false);
        $this->service_type = $auction->get->service_type ?? '';
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

        $this->auctionId = $auctionId;
        // Initialize arrays
        $this->website_link = [''];
        $this->reviews_links = [['url' => '']];
        $this->social_media = [['platform' => '', 'url' => '']];

        // Auto-fill Agent Information from user profile
        $user = Auth::user();
        if ($user) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name  = $user->last_name ?? '';
            $this->phone      = $user->phone ?? '';
            $this->email      = $user->email ?? '';
            $this->brokerage  = $user->brokerage ?? '';
            $this->license_no = $user->license_no ?? '';
            $this->nar_id     = $user->nar_id ?? '';

            // Auto-load Default Profile for new bids
            $defaultProfile = AgentDefaultProfile::findForAgentWithFallback(
                $user->id,
                'landlord',
                $this->property_type ?: 'residential'
            );
            if ($defaultProfile) {
                $this->defaultProfileExists = true;
                $dp = $defaultProfile->profile_data ?? [];
                $this->bio                 = $dp['bio'] ?? '';
                $this->why_hire_you        = $dp['why_hire_you'] ?? '';
                $this->what_sets_you_apart = $dp['what_sets_you_apart'] ?? '';
                $this->marketing_plan      = $dp['marketing_plan'] ?? '';
                $this->year_licensed       = $dp['year_licensed'] ?? '';
                if (!empty($dp['reviews_links']))    $this->reviews_links    = $dp['reviews_links'];
                if (!empty($dp['website_link']))     $this->website_link     = $dp['website_link'];
                if (!empty($dp['social_media']))     $this->social_media     = $dp['social_media'];
                if (!empty($dp['additional_details'])) $this->additional_details = $dp['additional_details'];
                if (!empty($dp['first_name']))        $this->first_name        = $dp['first_name'];
                if (!empty($dp['last_name']))         $this->last_name         = $dp['last_name'];
                if (!empty($dp['phone']))             $this->phone             = $dp['phone'];
                if (!empty($dp['email']))             $this->email             = $dp['email'];
                if (!empty($dp['brokerage']))         $this->brokerage         = $dp['brokerage'];
                if (!empty($dp['license_no']))        $this->license_no        = $dp['license_no'];
                if (!empty($dp['nar_id']))            $this->nar_id            = $dp['nar_id'];
                if (!empty($dp['presentation_link']))         $this->presentation_link         = $dp['presentation_link'];
                if (!empty($dp['business_card_link']))         $this->business_card_link         = $dp['business_card_link'];
                if (!empty($dp['business_card_stored_path'])) $this->business_card_stored_path  = $dp['business_card_stored_path'];
                if (!empty($dp['promoMaterials']))             $this->promoMaterials             = $dp['promoMaterials'];
                $this->defaultProfileLoaded = true;
            }
        }
    }

    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }

    public function goToNextStep(): void
    {
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

        $maxTab = $this->service_type === 'full_service' ? 5 : 4;
        if ($this->activeTab < $maxTab) {
            $this->activeTab = $this->activeTab + 1;
        }
    }

    public function goToPreviousStep(): void
    {
        if ($this->activeTab > 0) {
            $this->activeTab = $this->activeTab - 1;
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

        AgentDefaultProfile::upsertForAgent($user->id, 'landlord', $propType, $data);

        if (!AgentDefaultProfile::findRoleDefault($user->id, 'landlord')) {
            AgentDefaultProfile::upsertRoleDefault($user->id, 'landlord', $data);
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
        AgentDefaultProfile::upsertRoleDefault($user->id, 'landlord', $this->buildProfileData());

        $this->defaultProfileExists = true;
        $this->defaultProfileLoaded = true;
        session()->flash('success', 'Saved as your main Landlord default profile — it will pre-fill all property types that don\'t have their own override.');
    }

    public function loadDefaultProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $profile = AgentDefaultProfile::findForAgentWithFallback($user->id, 'landlord', $this->property_type ?: 'residential');
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
        if (!empty($data['presentation_link']))         $this->presentation_link         = $data['presentation_link'];
        if (!empty($data['business_card_link']))         $this->business_card_link         = $data['business_card_link'];
        if (!empty($data['business_card_stored_path'])) $this->business_card_stored_path  = $data['business_card_stored_path'];
        if (!empty($data['promoMaterials']))             $this->promoMaterials             = $data['promoMaterials'];
        $this->defaultProfileLoaded = true;
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
        \Log::info('[LandlordBid] submit() started', [
            'user_id'    => Auth::id(),
            'auction_id' => $this->auctionId,
            'active_tab' => $this->activeTab,
            'has_bio'    => !empty($this->bio),
            'has_why'    => !empty($this->why_hire_you),
            'has_what'   => !empty($this->what_sets_you_apart),
            'has_mktg'   => !empty($this->marketing_plan),
            'year_lic'   => $this->year_licensed,
            'promo_count'=> count($this->promoMaterials),
        ]);

        DB::beginTransaction();
        try {
            $this->validate();
            \Log::info('[LandlordBid] validation passed');

            $allowedVideos = ['mp4', 'mov', 'avi'];
            $allowedPhotos = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];

            $bid = new LandlordAgentAuctionBidData();
            $bid->user_id = Auth::id();
            $bid->landlord_agent_auction_id = $this->auctionId;
            $bid->save();



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

            $bid->saveMeta('services', json_encode($this->services));
            $bid->saveMeta('other_services', json_encode($this->other_services ?? null));

            $bid->saveMeta('other_services_enabled', $this->other_services_enabled);
            $bid->saveMeta('additional_details', $this->additional_details);
            // Broker Compensation & Agency Agreement Terms

            // Lease Option Agreement
            $bid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
            $bid->saveMeta('lease_type', $this->lease_type);
            $bid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
            $bid->saveMeta('purchase_type', $this->purchase_type);
            $bid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));

            // Landlord’s Broker Lease Fee (Residential and Commercial)
            $bid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
            $bid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
            $bid->saveMeta('purchase_fee_rental_period', $this->purchase_fee_rental_period);
            $bid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
            $bid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
            $bid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

            // Commercial fields
            $bid->saveMeta('purchase_fee_net_aggregate', $this->purchase_fee_net_aggregate);
            $bid->saveMeta('purchase_fee_gross_rent', $this->purchase_fee_gross_rent);
            $bid->saveMeta('sales_tax_option_gross', $this->sales_tax_option_gross);
            $bid->saveMeta('purchase_fee_monthly_percentage', $this->purchase_fee_monthly_percentage);
            $bid->saveMeta('purchase_fee_months', $this->purchase_fee_months);
            $bid->saveMeta('sales_tax_option_monthly', $this->sales_tax_option_monthly);
            $bid->saveMeta('purchase_fee_flat_commercial', $this->purchase_fee_flat_commercial);
            $bid->saveMeta('sales_tax_option_flat', $this->sales_tax_option_flat);
            $bid->saveMeta('purchase_fee_purchase_price', $this->purchase_fee_purchase_price);
            $bid->saveMeta('purchase_fee_other_commercial', $this->purchase_fee_other_commercial);

            // Interested in Selling
            $bid->saveMeta('interested_in_selling', $this->interested_in_selling);
            $bid->saveMeta('interested_in_selling_type', $this->interested_in_selling_type);
            $bid->saveMeta('landlord_broker_purchase_price', $this->landlord_broker_purchase_price);
            $bid->saveMeta('landlord_broker_percentage_price', $this->landlord_broker_percentage_price);
            $bid->saveMeta('landlord_broker_dollar_price', $this->landlord_broker_dollar_price);
            $bid->saveMeta('landlord_broker_flate_fee', $this->landlord_broker_flate_fee);
            $bid->saveMeta('landlord_broker_other', $this->landlord_broker_other);

            // Payment Timing for Broker Fees
            $bid->saveMeta('broker_fee_timing', $this->broker_fee_timing);
            $bid->saveMeta('broker_fee_days_from_rent', $this->broker_fee_days_from_rent);
            $bid->saveMeta('broker_fee_days_after_lease', $this->broker_fee_days_after_lease);
            $bid->saveMeta('broker_fee_days_after_rent', $this->broker_fee_days_after_rent);
            $bid->saveMeta('broker_fee_timing_other', $this->broker_fee_timing_other);
            $bid->saveMeta('split_payment_due', $this->split_payment_due);
            $bid->saveMeta('split_payment_due_other', $this->split_payment_due_other);
            $bid->saveMeta('broker_fee_days_after_due_event', $this->broker_fee_days_after_due_event);

            // Lease Renewal/Extension Fee
            $bid->saveMeta('renewal_fee_type', $this->renewal_fee_type);
            $bid->saveMeta('renewal_fee_percentage', $this->renewal_fee_percentage);
            $bid->saveMeta('renewal_fee_lease_value', $this->renewal_fee_lease_value);
            $bid->saveMeta('renewal_fee_first_month', $this->renewal_fee_first_month);
            $bid->saveMeta('renewal_fee_flat_free', $this->renewal_fee_flat_free);
            $bid->saveMeta('renewal_fee_custom', $this->renewal_fee_custom);
            $bid->saveMeta('renewal_fee_sales_tax_lease_value', $this->renewal_fee_sales_tax_lease_value);
            $bid->saveMeta('renewal_fee_sales_tax_flat_fee', $this->renewal_fee_sales_tax_flat_fee);
            $bid->saveMeta('renewal_fee_sales_tax_first_month', $this->renewal_fee_sales_tax_first_month);
            $bid->saveMeta('renewal_fee_no_of_months', $this->renewal_fee_no_of_months);

            // Expansion Commission for Lease Amendment (commercial only)
            $bid->saveMeta('expansion_commission_percentage', $this->expansion_commission_percentage);

            // Tenant's Broker Commission Fee (residential only)
            $bid->saveMeta('tenant_broker_commission_structure', $this->tenant_broker_commission_structure);
            $bid->saveMeta('tenant_broker_fee_structure', $this->tenant_broker_fee_structure);
            $bid->saveMeta('tenant_broker_percentage', $this->tenant_broker_percentage);
            $bid->saveMeta('tenant_broker_gross_lease', $this->tenant_broker_gross_lease);
            $bid->saveMeta('tenant_broker_first_month_rent', $this->tenant_broker_first_month_rent);
            $bid->saveMeta('tenant_broker_flat_fee', $this->tenant_broker_flat_fee);
            $bid->saveMeta('tenant_broker_other', $this->tenant_broker_other);

            // Protection Period Timeframe
            $bid->saveMeta('protection_period', $this->protection_period);

            // Early Termination Fee
            $bid->saveMeta('early_termination_fee_option', $this->early_termination_fee_option);
            $bid->saveMeta('early_termination_fee_amount', $this->early_termination_fee_amount);

            // Landlord Agency Agreement Timeframe
            $bid->saveMeta('agency_agreement_timeframe', $this->agency_agreement_timeframe);
            $bid->saveMeta('agency_agreement_custom', $this->agency_agreement_custom);

            // Interested in Property Management
            $bid->saveMeta('interested_in_property_management', $this->interested_in_property_management);
            $bid->saveMeta('interested_in_property_management_fee', $this->interested_in_property_management_fee);
            $bid->saveMeta('interested_in_property_management_fee_gross_lease', $this->interested_in_property_management_fee_gross_lease);
            $bid->saveMeta('interested_in_property_management_fee_rental_periord', $this->interested_in_property_management_fee_rental_periord);
            $bid->saveMeta('interested_in_property_management_fee_flate_free', $this->interested_in_property_management_fee_flate_free);
            $bid->saveMeta('interested_in_property_management_fee_other', $this->interested_in_property_management_fee_other);

            // Acceptable Brokerage Relationship
            $bid->saveMeta('brokerage_relationship', $this->brokerage_relationship);

            // Additional Terms
            $bid->saveMeta('additional_details_broker', $this->additional_details_broker);

            // $bid->saveMeta('custom_services', json_encode($this->custom_services));
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
            // Send Real-Time Notification
            $auction = \App\Models\LandlordAgentAuction::find($this->auctionId);

            // Your bid submission logic here...

            // Notify the auction owner
            $auctionOwner = $auction->user;
            $auctionOwner->notify(new BidSubmittedNotification($bid, $auction, 'landlord_agent'));
            DB::commit();
            session()->flash('success', 'Your bid has been submitted successfully!');

            return redirect()->route('landlord.agent.auction.view', $this->auctionId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::warning('[LandlordBid] ValidationException caught', ['errors' => $e->errors()]);
            $this->activeTab = 0;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[LandlordBid] Exception caught', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Error saving bid: ' . $e->getMessage());
        }
    }



    public function render()
    {
        return view('livewire.landlord.landlord-agent-auction-bid', [
            'auctionId' => $this->auctionId, // explicitly pass if you like
            'user_type' => $this->user_type, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }
}
