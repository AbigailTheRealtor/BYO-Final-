<?php

namespace App\Http\Livewire\Landlord;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\LandlordAgentAuctionBid as LandlordAgentAuctionBidData;
use App\Models\AgentDefaultProfile;
use App\Services\AgentBidMapperService;
use App\Helpers\LandlordBidMatchScoreHelper;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\BidSubmitted;
use App\Notifications\BidSubmittedNotification;
use App\Notifications\BidModifiedNotification;
use Illuminate\Support\Facades\Log;

class LandlordAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $service_type; // 'full_service' or 'limited_service'
    public $user_type;
    public $property_type;

    public $activeTab = 0;

    public $isEditMode = false;
    public $editBidId = null;

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
    public $social_media = [['platform' => '', 'url' => '']];
    public string $awards_recognition = '';
    public string $sold_listed_examples = '';
    public string $marketing_success_examples = '';
    public string $review_1 = '';
    public string $review_2 = '';
    public string $review_3 = '';




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
    public $renewal_fee_flat_fee = '';
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
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;
    public $interested_in_property_management = '';
    public $interested_in_property_management_fee = '';
    public $interested_in_property_management_fee_gross_lease = '';
    public $interested_in_property_management_fee_rental_periord = '';
    public $interested_in_property_management_fee_flate_free = '';
    public $interested_in_property_management_fee_other = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';
    public $retained_deposits = '';


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
            'first_name'  => 'required|string',
            'last_name'   => 'required|string',
            'phone'       => 'required|string',
            'email'       => 'required|email',
            'brokerage'   => 'required|string',
            'license_no'  => 'required|string',
            'referral_fee_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected array $messages = [
        'bio.required'                 => 'Please fill in "About Agent".',
        'why_hire_you.required'        => 'Please fill in "Why Should You Be Hired as Their Agent?".',
        'what_sets_you_apart.required' => 'Please fill in "What Sets You Apart From Other Agents?".',
        'marketing_plan.required'      => 'Please fill in "What Is Your Marketing Strategy?".',
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
            'renewal_fee_flat_fee',
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

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
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
    private function filterServicesToCurrentCatalog(array $services): array
    {
        $propType = $this->property_type ?: '';
        if ($propType === '') {
            return $services;
        }

        $catalog = LandlordBidMatchScoreHelper::getCatalog($propType);
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

        $auction = \App\Models\LandlordAgentAuction::find($auctionId);
        // $this->additional_details = $auction->get->additional_details ?? '';

        $this->services = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;
        $this->other_services = is_string($auction->get->other_services) ? json_decode($auction->get->other_services, true) ?? [] : (array)$auction->get->other_services;

        $rawPhotoEnhancements = $auction->get->photo_enhancements ?? null;
        $this->photo_enhancements = is_string($rawPhotoEnhancements) ? (json_decode($rawPhotoEnhancements, true) ?? []) : (is_array($rawPhotoEnhancements) ? $rawPhotoEnhancements : []);
        $this->custom_enhancement = $auction->get->custom_enhancement ?? '';
        $this->showEnhancements = in_array('Provide digital photo enhancements', $this->services);
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);

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
        $this->renewal_fee_flat_fee = $auction->get->renewal_fee_flat_fee ?? '';
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
        $this->referral_fee_percent = $auction->get->referral_percentage ?? '';
        $this->isListingCreatedByAgent = $auction->isCreatedByAgent();

        $this->auctionId = $auctionId;
        // Initialize arrays
        $this->website_link = [''];
        $this->reviews_links = [['url' => '']];
        $this->social_media = [['platform' => '', 'url' => '']];

        // Auto-fill Agent Information from user profile — new bids only
        $user = Auth::user();
        if (!$this->isEditMode && $user) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name  = $user->last_name ?? '';
            $this->phone      = $user->phone ?? '';
            $this->email      = $user->email ?? '';
            $this->brokerage  = $user->brokerage ?? '';
            $this->license_no = $user->license_no ?? '';
            $this->nar_id     = $user->nar_id ?? '';

            // Auto-load Default Profile for new bids
            $profile = AgentDefaultProfile::findForAgentWithFallback(
                $user->id,
                'landlord',
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
                $this->applyPresetField('review_1', $mapped['review_1'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('review_2', $mapped['review_2'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('review_3', $mapped['review_3'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('presentation_link', $mapped['presentation_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_link', $mapped['business_card_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_stored_path', $mapped['business_card_stored_path'] ?? null, $presetFieldsApplied);
                if (!empty($mapped['promoMaterials']))            $this->promoMaterials            = $mapped['promoMaterials'];
                if (!empty($mapped['services'])) {
                    $filtered = $this->filterServicesToCurrentCatalog($mapped['services']);
                    if (!empty($filtered)) {
                        $this->services = $filtered;
                        $this->showEnhancements = in_array('Provide digital photo enhancements', $this->services);
                    }
                }
                if (!empty($mapped['other_services']))            $this->other_services            = $mapped['other_services'];
                // Broker Compensation fields from preset
                $this->applyPresetField('purchase_fee_type', $mapped['purchase_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat', $mapped['purchase_fee_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_rental_period', $mapped['purchase_fee_rental_period'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_percentage_combo', $mapped['purchase_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat_combo', $mapped['purchase_fee_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_net_aggregate', $mapped['purchase_fee_net_aggregate'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_gross_rent', $mapped['purchase_fee_gross_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('sales_tax_option_gross', $mapped['sales_tax_option_gross'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_monthly_percentage', $mapped['purchase_fee_monthly_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_months', $mapped['purchase_fee_months'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('sales_tax_option_monthly', $mapped['sales_tax_option_monthly'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat_commercial', $mapped['purchase_fee_flat_commercial'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('sales_tax_option_flat', $mapped['sales_tax_option_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_other_commercial', $mapped['purchase_fee_other_commercial'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_commission_structure', $mapped['tenant_broker_commission_structure'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_fee_structure', $mapped['tenant_broker_fee_structure'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_percentage', $mapped['tenant_broker_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_gross_lease', $mapped['tenant_broker_gross_lease'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_first_month_rent', $mapped['tenant_broker_first_month_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_flat_fee', $mapped['tenant_broker_flat_fee'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('tenant_broker_other', $mapped['tenant_broker_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_timing', $mapped['broker_fee_timing'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_from_rent', $mapped['broker_fee_days_from_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_after_lease', $mapped['broker_fee_days_after_lease'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_days_after_rent', $mapped['broker_fee_days_after_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('broker_fee_timing_other', $mapped['broker_fee_timing_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_type', $mapped['renewal_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_percentage', $mapped['renewal_fee_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_lease_value', $mapped['renewal_fee_lease_value'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_first_month', $mapped['renewal_fee_first_month'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_flat_fee', $mapped['renewal_fee_flat_fee'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_custom', $mapped['renewal_fee_custom'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_sales_tax_lease_value', $mapped['renewal_fee_sales_tax_lease_value'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_no_of_months', $mapped['renewal_fee_no_of_months'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_sales_tax_first_month', $mapped['renewal_fee_sales_tax_first_month'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('renewal_fee_sales_tax_flat_fee', $mapped['renewal_fee_sales_tax_flat_fee'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('expansion_commission_percentage', $mapped['expansion_commission_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management', $mapped['interested_in_property_management'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management_fee', $mapped['interested_in_property_management_fee'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management_fee_gross_lease', $mapped['interested_in_property_management_fee_gross_lease'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management_fee_rental_periord', $mapped['interested_in_property_management_fee_rental_periord'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management_fee_flate_free', $mapped['interested_in_property_management_fee_flate_free'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_property_management_fee_other', $mapped['interested_in_property_management_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_selling', $mapped['interested_in_selling'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_in_selling_type', $mapped['interested_in_selling_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('landlord_broker_purchase_price', $mapped['landlord_broker_purchase_price'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('landlord_broker_percentage_price', $mapped['landlord_broker_percentage_price'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('landlord_broker_dollar_price', $mapped['landlord_broker_dollar_price'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('landlord_broker_flate_fee', $mapped['landlord_broker_flate_fee'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('landlord_broker_other', $mapped['landlord_broker_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_purchase_price', $mapped['purchase_fee_purchase_price'] ?? null, $presetFieldsApplied);
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
                            'role'                  => 'landlord',
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

        // --- Edit mode: load existing bid data ---
        if ($this->isEditMode && $this->editBidId) {
            $existingBid = LandlordAgentAuctionBidData::find($this->editBidId);

            if ($existingBid && $existingBid->user_id == Auth::id()) {
                if ($existingBid->accepted === 'accepted') {
                    session()->flash('error', 'Cannot edit an accepted bid.');
                    $this->isEditMode = false;
                    $this->editBidId  = null;
                    return redirect()->route('landlord.agent.auction.view', $auctionId);
                }
                if ($existingBid->accepted === 'rejected') {
                    session()->flash('error', 'Cannot edit a rejected bid.');
                    $this->isEditMode = false;
                    $this->editBidId  = null;
                    return redirect()->route('landlord.agent.auction.view', $auctionId);
                }

                $bidData = $existingBid->get;
                $user    = Auth::user();

                // Agent info — prefer saved bid data, fall back to user profile
                $this->first_name = (isset($bidData->first_name) && trim($bidData->first_name) !== '') ? $bidData->first_name : ($user->first_name ?? '');
                $this->last_name  = (isset($bidData->last_name)  && trim($bidData->last_name)  !== '') ? $bidData->last_name  : ($user->last_name  ?? '');
                $this->phone      = (isset($bidData->phone)       && trim($bidData->phone)       !== '') ? $bidData->phone       : ($user->phone       ?? '');
                $this->email      = (isset($bidData->email)       && trim($bidData->email)       !== '') ? $bidData->email       : ($user->email       ?? '');
                $this->brokerage  = (isset($bidData->brokerage)   && trim($bidData->brokerage)   !== '') ? $bidData->brokerage   : ($user->brokerage   ?? '');
                $this->license_no = (isset($bidData->license_no)  && trim($bidData->license_no)  !== '') ? $bidData->license_no  : ($user->license_no  ?? '');
                $this->nar_id     = (isset($bidData->nar_id)      && trim($bidData->nar_id)      !== '') ? $bidData->nar_id      : ($user->nar_id      ?? '');
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
                $this->areas_notes                = trim((string)($bidData->areas_notes                ?? ''));

                // Agent Overview
                $this->bio                 = $bidData->bio ?? '';
                $this->why_hire_you        = $bidData->why_hire_you ?? '';
                $this->what_sets_you_apart = $bidData->what_sets_you_apart ?? '';
                $this->marketing_plan      = $bidData->marketing_plan ?? '';
                $this->year_licensed       = $bidData->year_licensed ?? '';
                $this->additional_details  = $bidData->additional_details ?? '';

                // Reviews links
                $reviewsLinks = $bidData->reviews_links ?? '';
                if (is_string($reviewsLinks)) {
                    $decoded = json_decode($reviewsLinks, true);
                    $this->reviews_links = is_array($decoded) ? $decoded : [['url' => '']];
                } elseif (is_array($reviewsLinks) || is_object($reviewsLinks)) {
                    $rl = (array) $reviewsLinks;
                    $this->reviews_links = !empty($rl)
                        ? array_map(fn($r) => is_object($r) ? (array) $r : (is_array($r) ? $r : ['url' => '']), $rl)
                        : [['url' => '']];
                } else {
                    $this->reviews_links = [['url' => '']];
                }

                // Website link
                $websiteLink = $bidData->website_link ?? '';
                $this->website_link = is_array($websiteLink) ? $websiteLink : [$websiteLink];

                // Social media
                $socialMedia = $bidData->social_media ?? '';
                if (is_string($socialMedia)) {
                    $decoded = json_decode($socialMedia, true);
                    $this->social_media = is_array($decoded) ? $decoded : [['platform' => '', 'url' => '']];
                } elseif (is_array($socialMedia) || is_object($socialMedia)) {
                    $sm = (array) $socialMedia;
                    $this->social_media = !empty($sm)
                        ? array_map(fn($m) => is_object($m) ? (array) $m : (is_array($m) ? $m : ['platform' => '', 'url' => '']), $sm)
                        : [['platform' => '', 'url' => '']];
                } else {
                    $this->social_media = [['platform' => '', 'url' => '']];
                }

                $this->awards_recognition         = $bidData->awards_recognition         ?? '';
                $this->sold_listed_examples       = $bidData->sold_listed_examples       ?? '';
                $this->marketing_success_examples = $bidData->marketing_success_examples ?? '';
                $this->review_1                   = trim((string)($bidData->review_1                   ?? ''));
                $this->review_2                   = trim((string)($bidData->review_2                   ?? ''));
                $this->review_3                   = trim((string)($bidData->review_3                   ?? ''));

                // Services
                $services = $bidData->services ?? '';
                $this->services = is_string($services) ? (json_decode($services, true) ?? []) : (array) $services;

                $otherServices = $bidData->other_services ?? '';
                $this->other_services = is_string($otherServices) ? (json_decode($otherServices, true) ?? []) : (array) $otherServices;
                $this->other_services_enabled = (bool) ($bidData->other_services_enabled ?? false);

                $rawBidPhotoEnhancements = $bidData->photo_enhancements ?? null;
                $this->photo_enhancements = is_string($rawBidPhotoEnhancements) ? (json_decode($rawBidPhotoEnhancements, true) ?? []) : (is_array($rawBidPhotoEnhancements) ? $rawBidPhotoEnhancements : []);
                $this->custom_enhancement = $bidData->custom_enhancement ?? '';
                $this->showEnhancements = in_array('Provide digital photo enhancements', $this->services);
                $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);

                // Lease Option Agreement
                $this->interested_lease_option_agreement = $bidData->interested_lease_option_agreement ?? '';
                $this->lease_type  = $bidData->lease_type  ?? 'percent';
                $this->lease_value = $bidData->lease_value ?? '';
                $this->purchase_type  = $bidData->purchase_type  ?? 'percent';
                $this->purchase_value = $bidData->purchase_value ?? '';

                // Landlord's Broker Lease Fee (Residential)
                $this->purchase_fee_type              = $bidData->purchase_fee_type ?? '';
                $this->purchase_fee_flat              = $bidData->purchase_fee_flat ?? '';
                $this->purchase_fee_rental_period     = $bidData->purchase_fee_rental_period ?? '';
                $this->purchase_fee_percentage_combo  = $bidData->purchase_fee_percentage_combo ?? '';
                $this->purchase_fee_flat_combo        = $bidData->purchase_fee_flat_combo ?? '';
                $this->purchase_fee_other             = $bidData->purchase_fee_other ?? '';

                // Landlord's Broker Lease Fee (Commercial)
                $this->purchase_fee_net_aggregate       = $bidData->purchase_fee_net_aggregate ?? '';
                $this->purchase_fee_gross_rent          = $bidData->purchase_fee_gross_rent ?? '';
                $this->sales_tax_option_gross           = $bidData->sales_tax_option_gross ?? '';
                $this->purchase_fee_monthly_percentage  = $bidData->purchase_fee_monthly_percentage ?? '';
                $this->purchase_fee_months              = $bidData->purchase_fee_months ?? '';
                $this->sales_tax_option_monthly         = $bidData->sales_tax_option_monthly ?? '';
                $this->purchase_fee_flat_commercial     = $bidData->purchase_fee_flat_commercial ?? '';
                $this->sales_tax_option_flat            = $bidData->sales_tax_option_flat ?? '';
                $this->purchase_fee_purchase_price      = $bidData->purchase_fee_purchase_price ?? '';
                $this->purchase_fee_other_commercial    = $bidData->purchase_fee_other_commercial ?? '';

                // Interested in Selling
                $this->interested_in_selling              = $bidData->interested_in_selling ?? '';
                $this->interested_in_selling_type         = $bidData->interested_in_selling_type ?? '';
                $this->landlord_broker_purchase_price     = $bidData->landlord_broker_purchase_price ?? '';
                $this->landlord_broker_percentage_price   = $bidData->landlord_broker_percentage_price ?? '';
                $this->landlord_broker_dollar_price       = $bidData->landlord_broker_dollar_price ?? '';
                $this->landlord_broker_flate_fee          = $bidData->landlord_broker_flate_fee ?? '';
                $this->landlord_broker_other              = $bidData->landlord_broker_other ?? '';

                // Payment Timing
                $this->broker_fee_timing              = $bidData->broker_fee_timing ?? '';
                $this->broker_fee_days_from_rent      = $bidData->broker_fee_days_from_rent ?? '';
                $this->broker_fee_days_after_lease    = $bidData->broker_fee_days_after_lease ?? '';
                $this->broker_fee_days_after_rent     = $bidData->broker_fee_days_after_rent ?? '';
                $this->broker_fee_timing_other        = $bidData->broker_fee_timing_other ?? '';
                $this->split_payment_due              = $bidData->split_payment_due ?? '';
                $this->split_payment_due_other        = $bidData->split_payment_due_other ?? '';
                $this->broker_fee_days_after_due_event = $bidData->broker_fee_days_after_due_event ?? '';

                // Renewal/Extension Fees
                $this->renewal_fee_type                    = $bidData->renewal_fee_type ?? '';
                $this->renewal_fee_percentage              = $bidData->renewal_fee_percentage ?? '';
                $this->renewal_fee_lease_value             = $bidData->renewal_fee_lease_value ?? '';
                $this->renewal_fee_first_month             = $bidData->renewal_fee_first_month ?? '';
                $this->renewal_fee_flat_fee                = $bidData->renewal_fee_flat_fee ?? '';
                $this->renewal_fee_custom                  = $bidData->renewal_fee_custom ?? '';
                $this->renewal_fee_sales_tax_lease_value   = $bidData->renewal_fee_sales_tax_lease_value ?? '';
                $this->renewal_fee_no_of_months            = $bidData->renewal_fee_no_of_months ?? '';
                $this->renewal_fee_sales_tax_first_month   = $bidData->renewal_fee_sales_tax_first_month ?? '';
                $this->renewal_fee_sales_tax_flat_fee      = $bidData->renewal_fee_sales_tax_flat_fee ?? '';

                // Commercial Expansion Commission
                $this->expansion_commission_percentage = $bidData->expansion_commission_percentage ?? '';

                // Tenant's Broker Commission
                $this->tenant_broker_commission_structure = $bidData->tenant_broker_commission_structure ?? '';
                $this->tenant_broker_fee_structure        = $bidData->tenant_broker_fee_structure ?? '';
                $this->tenant_broker_percentage           = $bidData->tenant_broker_percentage ?? '';
                $this->tenant_broker_gross_lease          = $bidData->tenant_broker_gross_lease ?? '';
                $this->tenant_broker_first_month_rent     = $bidData->tenant_broker_first_month_rent ?? '';
                $this->tenant_broker_flat_fee             = $bidData->tenant_broker_flat_fee ?? '';
                $this->tenant_broker_other                = $bidData->tenant_broker_other ?? '';

                // Protection Period / Early Termination / Retainer / Agency Agreement / Property Mgmt
                $this->protection_period                                = $bidData->protection_period ?? '';
                $this->early_termination_fee_option                     = $bidData->early_termination_fee_option ?? '';
                $this->early_termination_fee_amount                     = $bidData->early_termination_fee_amount ?? '';
                $this->retainer_fee_option                              = $bidData->retainer_fee_option ?? '';
                $this->retainer_fee_amount                              = $bidData->retainer_fee_amount ?? '';
                $this->retainer_fee_application                         = $bidData->retainer_fee_application ?? '';
                $this->agency_agreement_timeframe                       = $bidData->agency_agreement_timeframe ?? '';
                $this->agency_agreement_custom                          = $bidData->agency_agreement_custom ?? '';
                $this->interested_in_property_management                = $bidData->interested_in_property_management ?? '';
                $this->interested_in_property_management_fee            = $bidData->interested_in_property_management_fee ?? '';
                $this->interested_in_property_management_fee_gross_lease     = $bidData->interested_in_property_management_fee_gross_lease ?? '';
                $this->interested_in_property_management_fee_rental_periord  = $bidData->interested_in_property_management_fee_rental_periord ?? '';
                $this->interested_in_property_management_fee_flate_free      = $bidData->interested_in_property_management_fee_flate_free ?? '';
                $this->interested_in_property_management_fee_other           = $bidData->interested_in_property_management_fee_other ?? '';

                // Brokerage Relationship / Additional Details
                $this->brokerage_relationship    = $bidData->brokerage_relationship ?? '';
                $this->additional_details_broker = $bidData->additional_details_broker ?? '';
                $this->referral_fee_percent      = $bidData->referral_fee_percent ?? '';

                // Presentation/Marketing links
                $this->presentation_link         = $bidData->presentation_link ?? '';
                $this->business_card_link        = $bidData->business_card_link ?? '';
                $this->business_card_stored_path = $bidData->business_card ?? null;
                $this->promo_materials_link      = $bidData->promo_materials_link ?? '';

                // Promo Materials
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
                    // Normalize: ensure each entry has all required keys
                    $this->promoMaterials = array_map(function ($m) {
                        if (is_object($m)) $m = (array) $m;
                        $m['type']  = $m['type']  ?? '';
                        $m['link']  = $m['link']  ?? '';
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
                // Bid not found or ownership mismatch — fall back to new-bid mode
                $this->isEditMode = false;
                $this->editBidId  = null;
            }
        }
    }

    public function setActiveTab($index)
    {
        $this->activeTab = $index;
    }

    public function hasReferralTab(): bool
    {
        return $this->isListingCreatedByAgent ?? false;
    }

    public function goToNextStep(): void
    {
        // Validate Agent Overview required fields when leaving Tab 0
        if ($this->activeTab === 0) {
            $this->validateOnly('bio', ['bio' => 'required|string'], $this->messages);
            $this->validateOnly('why_hire_you', ['why_hire_you' => 'required|string'], $this->messages);
            $this->validateOnly('what_sets_you_apart', ['what_sets_you_apart' => 'required|string'], $this->messages);
            $this->validateOnly('marketing_plan', ['marketing_plan' => 'required|string'], $this->messages);
            if ($this->getErrorBag()->hasAny(['bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan'])) {
                return;
            }
        }

        $maxTab = $this->service_type === 'full_service' ? ($this->hasReferralTab() ? 8 : 7) : 5;
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
                    $path = app(\App\Support\Storage\ListingStorageWriter::class)->storePublicAuto($file, 'auction/promo-materials');
                    if ($path) $storedPaths[] = $path;
                }
            }
            $this->promoMaterials[$i]['files'] = $storedPaths;
        }
        if ($this->business_card && is_object($this->business_card) && method_exists($this->business_card, 'store')) {
            $path = app(\App\Support\Storage\ListingStorageWriter::class)->storePublicAuto($this->business_card, 'auction/documents');
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
            'reviews_links'              => $this->reviews_links,
            'website_link'               => $this->website_link,
            'social_media'               => $this->social_media,
            'awards_recognition'         => $this->awards_recognition,
            'sold_listed_examples'       => $this->sold_listed_examples,
            'marketing_success_examples' => $this->marketing_success_examples,
            'review_1'                   => $this->review_1,
            'review_2'                   => $this->review_2,
            'review_3'                   => $this->review_3,
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
        $rawRl = $data['reviews_links'] ?? [['url' => '']];
        $this->reviews_links = array_values(array_map(function ($item) {
            if (is_object($item)) $item = (array) $item;
            if (!is_array($item)) return ['url' => ''];
            return ['url' => (!empty($item['url']) ? $item['url'] : ($item['text'] ?? ''))];
        }, $rawRl ?: [['url' => '']]));
        $this->website_link               = $data['website_link'] ?? '';
        $this->social_media               = $data['social_media'] ?? [['platform' => '', 'url' => '']];
        $this->awards_recognition         = $data['awards_recognition']         ?? '';
        $this->sold_listed_examples       = $data['sold_listed_examples']       ?? '';
        $this->marketing_success_examples = $data['marketing_success_examples'] ?? '';
        $this->review_1                   = trim($data['review_1']                   ?? '');
        $this->review_2                   = trim($data['review_2']                   ?? '');
        $this->review_3                   = trim($data['review_3']                   ?? '');
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
        $this->areas_notes                = trim($data['areas_notes']                ?? '');
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
            'is_edit'    => $this->isEditMode,
            'edit_bid_id'=> $this->editBidId,
            'active_tab' => $this->activeTab,
            'has_bio'    => !empty($this->bio),
            'has_why'    => !empty($this->why_hire_you),
            'has_what'   => !empty($this->what_sets_you_apart),
            'has_mktg'   => !empty($this->marketing_plan),
            'year_lic'   => $this->year_licensed,
            'promo_count'=> count($this->promoMaterials),
        ]);

        // BYA-H2 (Rule B1): the hire-agent listing owner may not submit an agent bid
        // on their own listing. Checked before validation so a self-bid is rejected
        // immediately; the listing owner and bidding agents are distinct accounts.
        $ownerCheckListing = \App\Models\LandlordAgentAuction::find($this->auctionId);
        if ($ownerCheckListing && (int) $ownerCheckListing->user_id === (int) Auth::id()) {
            session()->flash('error', 'You cannot submit an agent bid on your own listing.');
            return redirect()->route('landlord.agent.auction.view', $this->auctionId);
        }

        DB::beginTransaction();
        try {
            $this->validate();
            \Log::info('[LandlordBid] validation passed');

            // BYA-H6: an expired listing no longer accepts NEW bids. Editing an
            // already-placed bid is unaffected. Mirrors the accept/reject expiry
            // guards already enforced in the bid controllers (status === 'Expired'
            // is derived from the listing's expiration_date).
            if (!($this->isEditMode && $this->editBidId)) {
                $listingForExpiry = \App\Models\LandlordAgentAuction::find($this->auctionId);
                if ($listingForExpiry && $listingForExpiry->status === 'Expired') {
                    DB::rollBack();
                    session()->flash('error', 'This listing has expired and is no longer accepting new bids.');
                    return redirect()->route('landlord.agent.auction.view', $this->auctionId);
                }
            }

            $allowedVideos = ['mp4', 'mov', 'avi'];
            $allowedPhotos = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];

            if ($this->isEditMode && $this->editBidId) {
                // Edit mode: reuse existing bid record
                $bid = LandlordAgentAuctionBidData::find($this->editBidId);
                if (!$bid || $bid->user_id != Auth::id()) {
                    session()->flash('error', 'You cannot edit this bid.');
                    DB::rollBack();
                    return;
                }
                if ($bid->accepted === 'accepted' || $bid->accepted === 'rejected') {
                    session()->flash('error', 'Cannot edit a bid that has been accepted or rejected.');
                    DB::rollBack();
                    return;
                }
            } else {
                // New bid — backend safeguard: if user already has a bid, update it instead of inserting
                $bid = LandlordAgentAuctionBidData::where('landlord_agent_auction_id', $this->auctionId)
                    ->where('user_id', Auth::id())
                    ->first();
                if (!$bid) {
                    $bid = new LandlordAgentAuctionBidData();
                    $bid->user_id = Auth::id();
                    $bid->landlord_agent_auction_id = $this->auctionId;
                    $bid->save();
                }
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
            $bid->saveMeta('awards_recognition',        $this->awards_recognition);
            $bid->saveMeta('sold_listed_examples',      $this->sold_listed_examples);
            $bid->saveMeta('marketing_success_examples', $this->marketing_success_examples);
            $bid->saveMeta('review_1',                  trim($this->review_1));
            $bid->saveMeta('review_2',                  trim($this->review_2));
            $bid->saveMeta('review_3',                  trim($this->review_3));

            $bid->saveMeta('services', json_encode($this->services));
            $bid->saveMeta('other_services', json_encode($this->other_services ?? null));
            $bid->saveMeta('photo_enhancements', json_encode($this->photo_enhancements ?? []));
            $bid->saveMeta('custom_enhancement', $this->custom_enhancement ?? '');

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
            $bid->saveMeta('renewal_fee_flat_fee', $this->renewal_fee_flat_fee);
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

            // Retainer Fee
            $bid->saveMeta('retainer_fee_option', $this->retainer_fee_option);
            $bid->saveMeta('retainer_fee_amount', $this->retainer_fee_amount);
            $bid->saveMeta('retainer_fee_application', $this->retainer_fee_application);

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
            $bid->saveMeta('retained_deposits', $this->retained_deposits);
            $landlordAuction = \App\Models\LandlordAgentAuction::find($this->auctionId);
            if ($landlordAuction && $landlordAuction->isCreatedByAgent()) {
                $bid->saveMeta('referral_fee_percent', $this->referral_fee_percent);
            }

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
                    $path = app(\App\Support\Storage\ListingStorageWriter::class)->storePublicAuto($this->video_upload, 'auction/videos', $fileName);
                    $bid->saveMeta('video_upload', $path);
                }
            }

            // Handle business card upload
            if ($this->business_card && is_object($this->business_card) && method_exists($this->business_card, 'getClientOriginalExtension')) {
                $extension = $this->business_card->getClientOriginalExtension();
                if (in_array($extension, $allowedPhotos)) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $path = app(\App\Support\Storage\ListingStorageWriter::class)->storePublicAuto($this->business_card, 'auction/documents', $fileName);
                    $bid->saveMeta('business_card', $path);
                }
            } elseif (!empty($this->business_card_stored_path)) {
                // No new upload — preserve existing path (from default profile or edit mode)
                $bid->saveMeta('business_card', $this->business_card_stored_path);
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
                            // Preserve existing string paths (edit mode or default profile)
                            if (is_string($file) && !empty($file)) {
                                $stored[] = $file;
                                continue;
                            }
                            if (!is_object($file) || !method_exists($file, 'getClientOriginalExtension')) continue;
                            $ext = strtolower($file->getClientOriginalExtension());
                            if (!in_array($ext, $allowed, true)) continue;

                            $name = (string) Str::uuid() . '.' . $ext;
                            $path = app(\App\Support\Storage\ListingStorageWriter::class)->storePublicAuto($file, 'auction/promo-materials', $name);
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
            $bid->saveMeta('areas_notes',                trim($this->areas_notes));

            $bid->saveCompatibilityPreferences($this->compatibility_agent_response);

            $auction = \App\Models\LandlordAgentAuction::find($this->auctionId);

            DB::commit();

            // Notify the listing owner — different notification for edit vs new bid
            try {
                $auctionOwner = $auction->user;
                if ($auctionOwner) {
                    if ($this->isEditMode) {
                        $auctionOwner->notify(new BidModifiedNotification($bid, $auction));
                    } else {
                        $auctionOwner->notify(new BidSubmittedNotification($bid, $auction, 'landlord_agent'));
                    }
                }
            } catch (\Exception $e) {
                Log::error('[LandlordBid] Failed to send bid notification', [
                    'bid_id'    => $bid->id,
                    'auction_id'=> $this->auctionId,
                    'is_edit'   => $this->isEditMode,
                    'error'     => $e->getMessage(),
                ]);
            }

            $message = $this->isEditMode
                ? 'Your bid has been updated successfully!'
                : 'Your bid has been submitted successfully!';
            session()->flash('success', $message);

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
        return view('livewire.landlord.landlord-agent-auction-bid', [
            'auctionId' => $this->auctionId, // explicitly pass if you like
            'user_type' => $this->user_type, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }
}
