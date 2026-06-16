<?php

namespace App\Http\Livewire\Seller;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid as SellerAgentAuctionBidData;
use App\Models\AgentDefaultProfile;
use App\Services\AgentBidMapperService;
use App\Models\User;
use App\Notifications\BidSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SellerAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $service_type = 'full_service';
    public $user_type = 'seller';
    public $property_type;

    public $activeTab = 0;

    public bool $defaultProfileExists = false;
    public bool $defaultProfileLoaded = false;
    public array $compatibility_agent_response = [];

    /** Agent profile_data for Build 4 / Phase 1 match scoring sub-dimensions. */
    public array $agentProfileData = [];

    public $editBidId = null;
    public bool $isEditMode = false;

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

    // Additional Details
    public $additional_details;

    // Services
    public $services = [];
    public bool $other_services_enabled = false;
    public array $other_services = [];
    // Photo enhancement sub-fields (match listing creation component exactly)
    public $showEnhancements = false;
    public $photo_enhancements = [];
    public $showCustomEnhancement = false;
    public $custom_enhancement = '';
    public $showOpenHouseInput = false;
    public $openHouseCount = 0;

    // Seller Broker Compensation Fields
    public $purchase_fee_type = '';
    public $purchase_fee_flat = '';
    public $purchase_fee_percentage = '';
    public $purchase_fee_percentage_combo = '';
    public $purchase_fee_flat_combo = '';
    public $purchase_fee_other = '';
    public $nominal = '';

    // Buyer Broker Commission Structure
    public $commission_structure = '';
    public $commission_structure_type = '';
    public $commission_structure_type_fee_flat = '';
    public $commission_structure_type_fee_flat_combo = '';
    public $commission_structure_type_fee_percentage = '';
    public $commission_structure_type_fee_percentage_combo = '';
    public $commission_structure_type_fee_other = '';

    // Leasing
    public $interested_purchase_fee_type = '';
    public $seller_leasing_fee_type = '';
    public $seller_leasing_gross = '';
    public $seller_leasing_each_rental = '';
    public $seller_leasing_gross_rental = '';
    public $seller_leasing_gross_month_rent = '';
    public $seller_leasing_gross_no_of_months = '';
    public $seller_leasing_gross_percentage = '';
    public $seller_leasing_gross_flat_combo = '';
    public $seller_leasing_gross_percentage_combo = '';
    public $seller_leasing_gross_flat_net_combo = '';
    public $seller_leasing_gross_percentage_net_combo = '';
    public $sales_tax_option_gross = '';
    public $seller_leasing_gross_sales_tax_first_month = '';
    public $seller_leasing_gross_sales_tax_option_gross = '';
    public $seller_leasing_gross_sales_tax_flat_free_gross = '';
    public $seller_leasing_gross_purchase_fee_flat_amount = '';
    public $seller_leasing_gross_purchase_fee_other = '';
    public $seller_leasing_gross_other = '';

    // Lease-Option Agreement
    public $interested_lease_option_agreement = '';
    public $lease_type = 'percent';
    public $lease_value = '';
    public $purchase_type = 'percent';
    public $purchase_value = '';

    // Protection & Fees
    public $protection_period = '';
    public $early_termination_fee_option = '';
    public $early_termination_fee_amount = '';
    public $retainer_fee_option = '';
    public $retainer_fee_amount = '';
    public $retainer_fee_application = '';
    public $retained_deposits = '';
    public $referral_fee_percent = '';
    public $isListingCreatedByAgent = false;

    // Agency Agreement
    public $agency_agreement_timeframe = '';
    public $agency_agreement_custom = '';
    public $brokerage_relationship = '';
    public $additional_details_broker = '';

    // Presentation & Promotional Materials
    public $presentation_link;
    public $embedUrl;
    public $video_upload;
    public $business_card_link;
    public $business_card;
    public $business_card_stored_path = null;
    public array $promoMaterials = [];
    public array $deletedFiles = [];

    protected function rules(): array
    {
        return [
            'bio'                  => 'required|string',
            'why_hire_you'         => 'required|string',
            'what_sets_you_apart'  => 'required|string',
            'marketing_plan'       => 'required|string',
            'year_licensed'        => 'required|numeric|min:1900|max:' . date('Y'),
            'promoMaterials.*.type'  => ['nullable', 'string'],
            'promoMaterials.*.other' => ['nullable', 'string'],
            'promoMaterials.*.text'  => ['nullable', 'string'],
            'promoMaterials.*.files' => ['nullable', 'array'],
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

    public function mount($auctionId = null)
    {
        $this->promoMaterials = [
            ['type' => '', 'other' => '', 'link' => '', 'files' => []],
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

        $auction = SellerAgentAuction::find($auctionId);

        if (!$auction) {
            session()->flash('error', 'Listing not found.');
            return redirect()->route('home');
        }

        $this->auctionId    = $auctionId;
        $this->service_type = 'full_service';
        $this->user_type    = 'seller';

        // Normalize property type to short form expected by the compensation blade
        $rawPropType = $auction->get->property_type ?? '';
        $this->property_type = $this->normalizePropType($rawPropType);

        // Autopopulate services from auction listing (filtered to Seller catalog for this property type)
        $listingServices = $auction->get->services ?? [];
        if (is_string($listingServices)) {
            $listingServices = json_decode($listingServices, true) ?? [];
        }
        if (is_array($listingServices)) {
            $catalog = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($rawPropType ?: 'Residential Property');
            $listingServices = array_values(array_filter($listingServices, function($s) use ($catalog) {
                return in_array(\App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s), $catalog, true);
            }));
            $this->services = $listingServices;
        }

        $listingOtherServices = $auction->get->other_services ?? [];
        if (is_string($listingOtherServices)) {
            $listingOtherServices = json_decode($listingOtherServices, true) ?? [];
        }
        if (is_array($listingOtherServices) && !empty(array_filter($listingOtherServices))) {
            $this->other_services = array_values(array_filter($listingOtherServices));
            $this->other_services_enabled = true;
        }

        // Pre-load photo enhancements from listing (mirrors Landlord bid pattern)
        $rawPhotoEnh = $auction->get->photo_enhancements ?? null;
        $this->photo_enhancements = is_string($rawPhotoEnh)
            ? (json_decode($rawPhotoEnh, true) ?? [])
            : (is_array($rawPhotoEnh) ? $rawPhotoEnh : []);
        $this->custom_enhancement  = $auction->get->custom_enhancement ?? '';
        // Handle both standard and Vacant Land service name variants
        $this->showEnhancements    = in_array('Provide digital photo enhancements', $this->services)
                                  || in_array('Provide digital enhancements to media assets', $this->services);
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);

        // Prefill compensation from listing
        $l = $auction->get;
        $this->purchase_fee_type              = $l->purchase_fee_type ?? '';
        $this->purchase_fee_flat              = $l->purchase_fee_flat ?? '';
        $this->purchase_fee_percentage        = $l->purchase_fee_percentage ?? '';
        $this->purchase_fee_percentage_combo  = $l->purchase_fee_percentage_combo ?? '';
        $this->purchase_fee_flat_combo        = $l->purchase_fee_flat_combo ?? '';
        $this->purchase_fee_other             = $l->purchase_fee_other ?? '';
        $this->nominal                        = $l->nominal ?? '';
        $this->commission_structure           = $l->commission_structure ?? '';
        $this->commission_structure_type      = $l->commission_structure_type ?? '';
        $this->commission_structure_type_fee_flat              = $l->commission_structure_type_fee_flat ?? '';
        $this->commission_structure_type_fee_flat_combo        = $l->commission_structure_type_fee_flat_combo ?? '';
        $this->commission_structure_type_fee_percentage        = $l->commission_structure_type_fee_percentage ?? '';
        $this->commission_structure_type_fee_percentage_combo  = $l->commission_structure_type_fee_percentage_combo ?? '';
        $this->commission_structure_type_fee_other             = $l->commission_structure_type_fee_other ?? '';
        $this->interested_purchase_fee_type   = $l->interested_purchase_fee_type ?? '';
        $this->seller_leasing_fee_type        = $l->seller_leasing_fee_type ?? '';
        $this->seller_leasing_gross           = $l->seller_leasing_gross ?? '';
        $this->seller_leasing_each_rental     = $l->seller_leasing_each_rental ?? '';
        $this->seller_leasing_gross_rental    = $l->seller_leasing_gross_rental ?? '';
        $this->seller_leasing_gross_month_rent         = $l->seller_leasing_gross_month_rent ?? '';
        $this->seller_leasing_gross_no_of_months       = $l->seller_leasing_gross_no_of_months ?? '';
        $this->seller_leasing_gross_percentage         = $l->seller_leasing_gross_percentage ?? '';
        $this->seller_leasing_gross_flat_combo         = $l->seller_leasing_gross_flat_combo ?? '';
        $this->seller_leasing_gross_percentage_combo   = $l->seller_leasing_gross_percentage_combo ?? '';
        $this->seller_leasing_gross_flat_net_combo     = $l->seller_leasing_gross_flat_net_combo ?? '';
        $this->seller_leasing_gross_percentage_net_combo = $l->seller_leasing_gross_percentage_net_combo ?? '';
        $this->sales_tax_option_gross         = $l->sales_tax_option_gross ?? '';
        $this->seller_leasing_gross_sales_tax_first_month       = $l->seller_leasing_gross_sales_tax_first_month ?? '';
        $this->seller_leasing_gross_sales_tax_option_gross      = $l->seller_leasing_gross_sales_tax_option_gross ?? '';
        $this->seller_leasing_gross_sales_tax_flat_free_gross   = $l->seller_leasing_gross_sales_tax_flat_free_gross ?? '';
        $this->seller_leasing_gross_purchase_fee_flat_amount    = $l->seller_leasing_gross_purchase_fee_flat_amount ?? '';
        $this->seller_leasing_gross_purchase_fee_other          = $l->seller_leasing_gross_purchase_fee_other ?? '';
        $this->seller_leasing_gross_other     = $l->seller_leasing_gross_other ?? '';
        $this->interested_lease_option_agreement = $l->interested_lease_option_agreement ?? '';
        $this->lease_type                     = ($l->lease_type ?? '') ?: 'percent';
        $this->lease_value                    = $l->lease_value ?? '';
        $this->purchase_type                  = ($l->purchase_type ?? '') ?: 'percent';
        $this->purchase_value                 = $l->purchase_value ?? '';
        $this->protection_period              = $l->protection_period ?? '';
        $this->early_termination_fee_option   = $l->early_termination_fee_option ?? '';
        $this->early_termination_fee_amount   = $l->early_termination_fee_amount ?? '';
        $this->retainer_fee_option            = $l->retainer_fee_option ?? '';
        $this->retainer_fee_amount            = $l->retainer_fee_amount ?? '';
        $this->retainer_fee_application       = $l->retainer_fee_application ?? '';
        $this->retained_deposits              = $l->retained_deposits ?? '';
        $this->agency_agreement_timeframe     = $l->agency_agreement_timeframe ?? '';
        $this->agency_agreement_custom        = $l->agency_agreement_custom ?? '';
        $this->brokerage_relationship         = $l->brokerage_relationship ?? '';
        $this->additional_details_broker      = $l->additional_details_broker ?? '';
        $this->referral_fee_percent           = $l->referral_percentage ?? '';
        $this->isListingCreatedByAgent        = $auction->isCreatedByAgent();

        // Initialize arrays
        $this->website_link  = [''];
        $this->reviews_links = [['text' => '']];
        $this->social_media  = [['platform' => '', 'text' => '']];

        // Auto-fill agent info and default profile
        $user = Auth::user();
        if (!$this->isEditMode && $user) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name  = $user->last_name ?? '';
            $this->phone      = $user->phone ?? '';
            $this->email      = $user->email ?? '';
            $this->brokerage  = $user->brokerage ?? '';
            $this->license_no = $user->license_no ?? '';
            $this->nar_id     = $user->nar_id ?? '';

            $profile = AgentDefaultProfile::findForAgentWithFallback(
                $user->id,
                'seller',
                strtolower(str_replace(' ', '_', $this->property_type)) ?: 'residential'
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
                $this->applyPresetField('presentation_link', $mapped['presentation_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_link', $mapped['business_card_link'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('business_card_stored_path', $mapped['business_card_stored_path'] ?? null, $presetFieldsApplied);
                if (!empty($mapped['promoMaterials']))            $this->promoMaterials            = $mapped['promoMaterials'];
                // Services from preset — blank-value guard: only fill if listing did not pre-populate them
                if (!empty($mapped['services']) && empty($this->services)) {
                    $catalog = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($rawPropType ?: 'Residential Property');
                    $normalizeService = static function (string $s): string {
                        return mb_strtolower(trim(str_replace(
                            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "'"],
                            ["'",        "'",        '"',        '"',        "'"],
                            $s
                        )));
                    };
                    $filtered = array_values(array_filter((array) $mapped['services'], static fn ($s) =>
                        in_array($normalizeService((string) $s), $catalog, true)
                    ));
                    if (!empty($filtered)) {
                        $this->services = $filtered;
                        $this->showEnhancements = in_array('Provide digital photo enhancements', $this->services)
                                               || in_array('Provide digital enhancements to media assets', $this->services);
                    }
                }
                // Broker Compensation fields from preset
                $this->applyPresetField('purchase_fee_type', $mapped['purchase_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat', $mapped['purchase_fee_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_percentage', $mapped['purchase_fee_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_percentage_combo', $mapped['purchase_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_flat_combo', $mapped['purchase_fee_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('purchase_fee_other', $mapped['purchase_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('nominal', $mapped['nominal'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure', $mapped['commission_structure'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type', $mapped['commission_structure_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type_fee_flat', $mapped['commission_structure_type_fee_flat'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type_fee_flat_combo', $mapped['commission_structure_type_fee_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type_fee_percentage', $mapped['commission_structure_type_fee_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type_fee_percentage_combo', $mapped['commission_structure_type_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('commission_structure_type_fee_other', $mapped['commission_structure_type_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('interested_purchase_fee_type', $mapped['interested_purchase_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_fee_type', $mapped['seller_leasing_fee_type'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross', $mapped['seller_leasing_gross'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_rental', $mapped['seller_leasing_gross_rental'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_month_rent', $mapped['seller_leasing_gross_month_rent'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_other', $mapped['seller_leasing_gross_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_percentage', $mapped['seller_leasing_gross_percentage'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_purchase_fee_flat_amount', $mapped['seller_leasing_gross_purchase_fee_flat_amount'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_purchase_fee_other', $mapped['seller_leasing_gross_purchase_fee_other'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_each_rental', $mapped['seller_leasing_each_rental'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_no_of_months', $mapped['seller_leasing_gross_no_of_months'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_flat_combo', $mapped['seller_leasing_gross_flat_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_percentage_combo', $mapped['seller_leasing_gross_percentage_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_flat_net_combo', $mapped['seller_leasing_gross_flat_net_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_percentage_net_combo', $mapped['seller_leasing_gross_percentage_net_combo'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_sales_tax_first_month', $mapped['seller_leasing_gross_sales_tax_first_month'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_sales_tax_option_gross', $mapped['seller_leasing_gross_sales_tax_option_gross'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('seller_leasing_gross_sales_tax_flat_free_gross', $mapped['seller_leasing_gross_sales_tax_flat_free_gross'] ?? null, $presetFieldsApplied);
                $this->applyPresetField('sales_tax_option_gross', $mapped['sales_tax_option_gross'] ?? null, $presetFieldsApplied);
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
                            'role'                  => 'seller',
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

        // Edit mode: load existing bid data if ?edit=bidId is in the URL
        $editBidId = request()->query('edit');
        if ($editBidId) {
            $existingBid = \App\Models\SellerAgentAuctionBid::with('meta')
                ->where('id', $editBidId)
                ->where('seller_agent_auction_id', $this->auctionId)
                ->where('user_id', Auth::id())
                ->first();

            if ($existingBid) {
                $this->editBidId   = $existingBid->id;
                $this->isEditMode  = true;
                $m = (array) $existingBid->get;

                $strFields = [
                    'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
                    'year_licensed', 'additional_details',
                    'purchase_fee_type', 'purchase_fee_flat', 'purchase_fee_percentage',
                    'purchase_fee_percentage_combo', 'purchase_fee_flat_combo', 'purchase_fee_other',
                    'nominal', 'commission_structure', 'commission_structure_type',
                    'commission_structure_type_fee_flat', 'commission_structure_type_fee_flat_combo',
                    'commission_structure_type_fee_percentage', 'commission_structure_type_fee_percentage_combo',
                    'commission_structure_type_fee_other',
                    'interested_purchase_fee_type', 'seller_leasing_fee_type',
                    'seller_leasing_gross', 'seller_leasing_each_rental', 'seller_leasing_gross_rental',
                    'seller_leasing_gross_month_rent', 'seller_leasing_gross_no_of_months',
                    'seller_leasing_gross_percentage', 'seller_leasing_gross_flat_combo',
                    'seller_leasing_gross_percentage_combo', 'seller_leasing_gross_flat_net_combo',
                    'seller_leasing_gross_percentage_net_combo', 'sales_tax_option_gross',
                    'seller_leasing_gross_sales_tax_first_month', 'seller_leasing_gross_sales_tax_option_gross',
                    'seller_leasing_gross_sales_tax_flat_free_gross', 'seller_leasing_gross_purchase_fee_flat_amount',
                    'seller_leasing_gross_purchase_fee_other', 'seller_leasing_gross_other',
                    'interested_lease_option_agreement', 'lease_type', 'lease_value',
                    'purchase_type', 'purchase_value', 'protection_period',
                    'early_termination_fee_option', 'early_termination_fee_amount',
                    'retainer_fee_option', 'retainer_fee_amount', 'retainer_fee_application',
                    'retained_deposits', 'agency_agreement_timeframe', 'agency_agreement_custom',
                    'brokerage_relationship', 'additional_details_broker',
                    'first_name', 'last_name', 'phone', 'email', 'brokerage', 'license_no', 'nar_id',
                    'presentation_link', 'business_card_link', 'business_card_stored_path',
                    'custom_enhancement', 'referral_fee_percent',
                ];
                foreach ($strFields as $field) {
                    if (array_key_exists($field, $m) && !is_null($m[$field])) {
                        $this->$field = is_object($m[$field]) ? (string)$m[$field] : $m[$field];
                    }
                }

                // Fallback: if business_card meta key holds a stored file path, populate
                // business_card_stored_path so re-saves preserve it without re-uploading
                if (empty($this->business_card_stored_path) && !empty($m['business_card']) && is_string($m['business_card'])) {
                    $this->business_card_stored_path = $m['business_card'];
                }

                // Services (catalog-filtered to prevent cross-role contamination)
                $editSvcs = $m['services'] ?? [];
                if (is_string($editSvcs)) { $editSvcs = json_decode($editSvcs, true) ?? []; }
                if (is_array($editSvcs)) {
                    $editCatalog = \App\Helpers\SellerBidMatchScoreHelper::getCatalog($rawPropType ?? 'Residential Property');
                    $editSvcs = array_values(array_filter($editSvcs, function($s) use ($editCatalog) {
                        return in_array(\App\Helpers\SellerBidMatchScoreHelper::normalizeService((string)$s), $editCatalog, true);
                    }));
                    $this->services = $editSvcs;
                }

                $editOtherSvcs = $m['other_services'] ?? [];
                if (is_string($editOtherSvcs)) { $editOtherSvcs = json_decode($editOtherSvcs, true) ?? []; }
                if (is_array($editOtherSvcs)) {
                    $this->other_services = array_values(array_filter($editOtherSvcs));
                    $this->other_services_enabled = !empty($this->other_services);
                }

                $editPhotoEnh = $m['photo_enhancements'] ?? [];
                if (is_string($editPhotoEnh)) { $editPhotoEnh = json_decode($editPhotoEnh, true) ?? []; }
                if (is_array($editPhotoEnh)) { $this->photo_enhancements = $editPhotoEnh; }
                $this->showEnhancements    = in_array('Provide digital photo enhancements', $this->services)
                                          || in_array('Provide digital enhancements to media assets', $this->services);
                $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);

                // Array fields
                foreach (['reviews_links', 'social_media', 'website_link'] as $arrField) {
                    if (!empty($m[$arrField])) {
                        $val = $m[$arrField];
                        if (is_string($val)) { $val = json_decode($val, true) ?? $val; }
                        // Normalize: convert any stdClass items to plain arrays
                        if (is_array($val)) {
                            $val = array_map(fn($item) => is_object($item) ? (array) $item : $item, $val);
                        }
                        $this->$arrField = is_array($val) ? $val : [$val];
                    }
                }

                // Promo materials
                $editPromo = $m['promo_materials'] ?? [];
                if (is_string($editPromo)) { $editPromo = json_decode($editPromo, true) ?? []; }
                if (is_array($editPromo) && !empty($editPromo)) {
                    // Normalize each item and its nested 'files' array from stdClass to plain array
                    $editPromo = array_map(function($item) {
                        $item = is_object($item) ? (array) $item : (is_array($item) ? $item : []);
                        if (isset($item['files']) && is_array($item['files'])) {
                            $item['files'] = array_map(fn($f) => is_object($f) ? (array) $f : $f, $item['files']);
                        }
                        return $item;
                    }, $editPromo);
                    $this->promoMaterials = $editPromo;
                }

                // Compatibility preferences from saved bid
                $this->compatibility_agent_response = array_merge(
                    $this->compatibility_agent_response,
                    $existingBid->loadCompatibilityPreferences()
                );
            }
        }
    }

    private function normalizePropType(string $raw): string
    {
        $raw = trim($raw);
        if (stripos($raw, 'Income') !== false) return 'Income';
        if (stripos($raw, 'Commercial') !== false) return 'Commercial';
        if (stripos($raw, 'Business') !== false) return 'Business';
        if (stripos($raw, 'Vacant') !== false) return 'Vacant Land';
        if (stripos($raw, 'Residential') !== false) return 'Residential';
        return $raw;
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
        if ($this->activeTab === 0) {
            $this->validateOnly('bio',                ['bio'                 => 'required|string'], $this->messages);
            $this->validateOnly('why_hire_you',       ['why_hire_you'        => 'required|string'], $this->messages);
            $this->validateOnly('what_sets_you_apart',['what_sets_you_apart' => 'required|string'], $this->messages);
            $this->validateOnly('marketing_plan',     ['marketing_plan'      => 'required|string'], $this->messages);
            $this->validateOnly('year_licensed',      ['year_licensed'       => 'required|numeric|min:1900|max:' . date('Y')], $this->messages);

            if ($this->getErrorBag()->hasAny(['bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan', 'year_licensed'])) {
                return;
            }
        }

        $maxTab = $this->hasReferralTab() ? 7 : 6;
        if ($this->activeTab < $maxTab) {
            $this->activeTab++;
        }
    }

    public function goToPreviousStep(): void
    {
        if ($this->activeTab > 0) {
            $this->activeTab--;
        }
    }

    public function setType(string $which, string $type): void
    {
        if ($which === 'lease') {
            $this->lease_type  = $type ?: 'percent';
            $this->lease_value = '';
        } elseif ($which === 'purchase') {
            $this->purchase_type  = $type ?: 'percent';
            $this->purchase_value = '';
        }
    }

    public function updatedAgencyAgreementTimeframe($value)
    {
        $this->agency_agreement_custom = '';
    }

    // Mirrors LandlordAgentAuctionBid: keep showCustomEnhancement in sync with checkbox state
    public function updatedPhotoEnhancements()
    {
        $this->showCustomEnhancement = in_array('Other', $this->photo_enhancements);
    }

    public function updatedEarlyTerminationFeeOption($value)
    {
        $this->early_termination_fee_amount = '';
    }

    public function updatedRetainerFeeOption($value)
    {
        $this->reset(['retainer_fee_amount', 'retainer_fee_application']);
    }

    public function updatedInterestedPurchaseFeeType($value)
    {
        $this->seller_leasing_fee_type = '';
    }

    public function updatedPresentationLink($value): void
    {
        $this->embedUrl = $this->getEmbedUrl($value);
    }

    public function getEmbedUrl($url): ?string
    {
        if (empty($url)) return null;
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/i', $url, $m);
            $videoId = $m[1] ?? null;
            return $videoId ? "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&playsinline=1" : null;
        }
        if (str_contains($url, 'vimeo.com')) {
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)(?:[\/?#]|$)/i', $url, $m);
            $videoId = $m[1] ?? null;
            return $videoId ? "https://player.vimeo.com/video/{$videoId}?autoplay=1&muted=1&playsinline=1" : null;
        }
        return null;
    }

    public function updatePlaceholder($index, $platform)
    {
        $placeholders = [
            'Facebook'  => 'Enter profile link (e.g., https://www.facebook.com/yourhandle)',
            'Instagram' => 'Enter profile link (e.g., https://www.instagram.com/yourhandle)',
            'LinkedIn'  => 'Enter profile link (e.g., https://www.linkedin.com/in/yourhandle)',
            'TikTok'    => 'Enter profile link (e.g., https://www.tiktok.com/@yourhandle)',
            'X'         => 'Enter profile link (e.g., https://www.x.com/yourhandle)',
            'YouTube'   => 'Enter profile link (e.g., https://www.youtube.com/c/yourchannel)',
        ];
        $this->social_media[$index]['placeholder'] = $placeholders[$platform] ?? 'Enter profile link';
    }

    public function addSocialMedia()
    {
        $this->social_media[] = ['platform' => '', 'text' => '', 'placeholder' => 'Enter profile link'];
    }

    public function removeSocialMedia($index)
    {
        unset($this->social_media[$index]);
        $this->social_media = array_values($this->social_media);
    }

    public function addReviewLink()
    {
        $this->reviews_links[] = ['text' => ''];
    }

    public function removeReviewLink($index)
    {
        if (count($this->reviews_links) > 1) {
            array_splice($this->reviews_links, $index, 1);
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

    public function addServiceField(): void
    {
        $this->other_services[] = '';
    }

    public function removeService(int $index): void
    {
        unset($this->other_services[$index]);
        $this->other_services = array_values($this->other_services);
    }

    public function addMaterial(): void
    {
        $this->promoMaterials[] = ['type' => '', 'other' => '', 'link' => '', 'files' => []];
        $this->promoMaterials   = array_values($this->promoMaterials);
    }

    public function removeMaterial(int $index): void
    {
        if (count($this->promoMaterials) > 1) {
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

    public function updated($name, $value): void
    {
        if (empty($name)) return;

        if (str_ends_with($name, '.type')) {
            $parts = explode('.', $name);
            $i = (int) ($parts[1] ?? 0);
            if (($this->promoMaterials[$i]['type'] ?? '') !== 'Other') {
                $this->promoMaterials[$i]['other'] = '';
            }
        }
    }

    public function updatedReferralFeePercent(): void
    {
        $this->validateOnly('referral_fee_percent');
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
                    $path = $file->store('auction/documents', 'public');
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

        AgentDefaultProfile::upsertForAgent($user->id, 'seller', $propType, $data);

        if (!AgentDefaultProfile::findRoleDefault($user->id, 'seller')) {
            AgentDefaultProfile::upsertRoleDefault($user->id, 'seller', $data);
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
        AgentDefaultProfile::upsertRoleDefault($user->id, 'seller', $this->buildProfileData());

        $this->defaultProfileExists = true;
        $this->defaultProfileLoaded = true;
        session()->flash('success', 'Saved as your main Seller default profile — it will pre-fill all property types that don\'t have their own override.');
    }

    public function loadDefaultProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $profile = AgentDefaultProfile::findForAgentWithFallback($user->id, 'seller', strtolower(str_replace(' ', '_', $this->property_type)) ?: 'residential');
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
        // Compatibility preferences from profile
        $compatData = AgentBidMapperService::mapCompatibilityFromProfile($data);
        foreach ($compatData as $_cpSection => $_cpData) {
            if (is_array($_cpData) && !empty($_cpData)) {
                $this->compatibility_agent_response[$_cpSection] = $_cpData;
            }
        }
        $this->defaultProfileLoaded = true;
    }

    public function submit()
    {
        DB::beginTransaction();
        try {
            $this->validate();

            if ($this->isEditMode && $this->editBidId) {
                $bid = SellerAgentAuctionBidData::where('id', $this->editBidId)
                    ->where('user_id', Auth::id())
                    ->where('seller_agent_auction_id', $this->auctionId)
                    ->firstOrFail();
            } else {
                $bid = new SellerAgentAuctionBidData();
                $bid->user_id = Auth::id();
                $bid->seller_agent_auction_id = $this->auctionId;
                $bid->save();
            }

            // Agent Overview
            $bid->saveMeta('bio',                $this->bio);
            $bid->saveMeta('why_hire_you',        $this->why_hire_you);
            $bid->saveMeta('what_sets_you_apart', $this->what_sets_you_apart);
            $bid->saveMeta('marketing_plan',      $this->marketing_plan);
            $bid->saveMeta('reviews_links',       json_encode($this->reviews_links));
            $bid->saveMeta('website_link',        is_array($this->website_link) ? ($this->website_link[0] ?? '') : $this->website_link);
            $bid->saveMeta('social_media',        json_encode($this->social_media));
            $bid->saveMeta('year_licensed',       $this->year_licensed);

            // Services
            $bid->saveMeta('services',             json_encode($this->services));
            $bid->saveMeta('other_services',       json_encode($this->other_services ?? []));
            $bid->saveMeta('other_services_enabled', $this->other_services_enabled);
            $bid->saveMeta('photo_enhancements',   json_encode($this->photo_enhancements ?? []));
            $bid->saveMeta('custom_enhancement',   $this->custom_enhancement ?? '');

            // Additional Details
            $bid->saveMeta('additional_details',   $this->additional_details);

            // Broker Compensation
            $bid->saveMeta('purchase_fee_type',              $this->purchase_fee_type);
            $bid->saveMeta('purchase_fee_flat',              $this->purchase_fee_flat);
            $bid->saveMeta('purchase_fee_percentage',        $this->purchase_fee_percentage);
            $bid->saveMeta('purchase_fee_percentage_combo',  $this->purchase_fee_percentage_combo);
            $bid->saveMeta('purchase_fee_flat_combo',        $this->purchase_fee_flat_combo);
            $bid->saveMeta('purchase_fee_other',             $this->purchase_fee_other);
            $bid->saveMeta('nominal',                        $this->nominal);
            $bid->saveMeta('commission_structure',           $this->commission_structure);
            $bid->saveMeta('commission_structure_type',      $this->commission_structure_type);
            $bid->saveMeta('commission_structure_type_fee_flat',              $this->commission_structure_type_fee_flat);
            $bid->saveMeta('commission_structure_type_fee_flat_combo',        $this->commission_structure_type_fee_flat_combo);
            $bid->saveMeta('commission_structure_type_fee_percentage',        $this->commission_structure_type_fee_percentage);
            $bid->saveMeta('commission_structure_type_fee_percentage_combo',  $this->commission_structure_type_fee_percentage_combo);
            $bid->saveMeta('commission_structure_type_fee_other',             $this->commission_structure_type_fee_other);
            $bid->saveMeta('interested_purchase_fee_type',   $this->interested_purchase_fee_type);
            $bid->saveMeta('seller_leasing_fee_type',        $this->seller_leasing_fee_type);
            $bid->saveMeta('seller_leasing_gross',           $this->seller_leasing_gross);
            $bid->saveMeta('seller_leasing_each_rental',     $this->seller_leasing_each_rental);
            $bid->saveMeta('seller_leasing_gross_rental',    $this->seller_leasing_gross_rental);
            $bid->saveMeta('seller_leasing_gross_month_rent',$this->seller_leasing_gross_month_rent);
            $bid->saveMeta('seller_leasing_gross_no_of_months', $this->seller_leasing_gross_no_of_months);
            $bid->saveMeta('seller_leasing_gross_percentage',$this->seller_leasing_gross_percentage);
            $bid->saveMeta('seller_leasing_gross_flat_combo',$this->seller_leasing_gross_flat_combo);
            $bid->saveMeta('seller_leasing_gross_percentage_combo', $this->seller_leasing_gross_percentage_combo);
            $bid->saveMeta('seller_leasing_gross_flat_net_combo',   $this->seller_leasing_gross_flat_net_combo);
            $bid->saveMeta('seller_leasing_gross_percentage_net_combo', $this->seller_leasing_gross_percentage_net_combo);
            $bid->saveMeta('sales_tax_option_gross',          $this->sales_tax_option_gross);
            $bid->saveMeta('seller_leasing_gross_sales_tax_first_month',     $this->seller_leasing_gross_sales_tax_first_month);
            $bid->saveMeta('seller_leasing_gross_sales_tax_option_gross',    $this->seller_leasing_gross_sales_tax_option_gross);
            $bid->saveMeta('seller_leasing_gross_sales_tax_flat_free_gross', $this->seller_leasing_gross_sales_tax_flat_free_gross);
            $bid->saveMeta('seller_leasing_gross_purchase_fee_flat_amount',  $this->seller_leasing_gross_purchase_fee_flat_amount);
            $bid->saveMeta('seller_leasing_gross_purchase_fee_other',        $this->seller_leasing_gross_purchase_fee_other);
            $bid->saveMeta('seller_leasing_gross_other',     $this->seller_leasing_gross_other);
            $bid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);
            $bid->saveMeta('lease_type',                     $this->lease_type);
            $bid->saveMeta('lease_value',                    str_replace(',', '', $this->lease_value ?? ''));
            $bid->saveMeta('purchase_type',                  $this->purchase_type);
            $bid->saveMeta('purchase_value',                 str_replace(',', '', $this->purchase_value ?? ''));
            $bid->saveMeta('protection_period',              $this->protection_period);
            $bid->saveMeta('early_termination_fee_option',   $this->early_termination_fee_option);
            $bid->saveMeta('early_termination_fee_amount',   $this->early_termination_fee_amount);
            $bid->saveMeta('retainer_fee_option',            $this->retainer_fee_option);
            $bid->saveMeta('retainer_fee_amount',            $this->retainer_fee_amount);
            $bid->saveMeta('retainer_fee_application',       $this->retainer_fee_application);
            $bid->saveMeta('retained_deposits',              $this->retained_deposits);
            $bid->saveMeta('agency_agreement_timeframe',     $this->agency_agreement_timeframe);
            $bid->saveMeta('agency_agreement_custom',        $this->agency_agreement_custom);
            $bid->saveMeta('brokerage_relationship',         $this->brokerage_relationship);
            $bid->saveMeta('additional_details_broker',      $this->additional_details_broker);
            $sellerAuction = \App\Models\SellerAgentAuction::find($this->auctionId);
            if ($sellerAuction && $sellerAuction->isCreatedByAgent()) {
                $bid->saveMeta('referral_fee_percent', $this->referral_fee_percent);
            }

            // Agent Credentials
            $bid->saveMeta('first_name',  $this->first_name);
            $bid->saveMeta('last_name',   $this->last_name);
            $bid->saveMeta('phone',       $this->phone);
            $bid->saveMeta('email',       $this->email);
            $bid->saveMeta('brokerage',   $this->brokerage);
            $bid->saveMeta('license_no',  $this->license_no);
            $bid->saveMeta('nar_id',      $this->nar_id);

            // Presentation Materials
            $bid->saveMeta('presentation_link', $this->presentation_link);
            $bid->saveMeta('business_card_link', $this->business_card_link);

            // Business card upload
            if ($this->business_card && is_object($this->business_card) && method_exists($this->business_card, 'store')) {
                $cardPath = $this->business_card->store('auction/documents', 'public');
                $bid->saveMeta('business_card', $cardPath);
            } elseif (!empty($this->business_card_stored_path)) {
                // No new file uploaded — persist the pre-saved / previously stored path
                $bid->saveMeta('business_card', $this->business_card_stored_path);
            }

            // Promo materials
            $savedMaterials = [];
            foreach ($this->promoMaterials as $idx => $item) {
                $entry = [
                    'type'  => $item['type'] ?? '',
                    'other' => $item['other'] ?? '',
                    'link'  => $item['link'] ?? '',
                    'files' => [],
                ];
                $files = $item['files'] ?? [];
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (is_string($file) && !empty($file)) {
                            $entry['files'][] = $file;
                        } elseif (is_object($file) && method_exists($file, 'store')) {
                            $path = $file->store('auction/documents', 'public');
                            $entry['files'][] = $path;
                        }
                    }
                }
                $savedMaterials[] = $entry;
            }
            $bid->saveMeta('promo_materials', json_encode($savedMaterials));

            $bid->saveCompatibilityPreferences($this->compatibility_agent_response);

            DB::commit();

            if (!$this->isEditMode) {
                try {
                    $auction = SellerAgentAuction::find($this->auctionId);
                    if ($auction) {
                        $listingOwner = User::find($auction->user_id);
                        if ($listingOwner) {
                            $listingOwner->notify(new BidSubmittedNotification($bid, $auction, 'seller_agent'));
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('[SellerBid] Failed to send bid submitted notification', ['error' => $e->getMessage()]);
                }
            }

            $msg = $this->isEditMode ? 'Your bid has been updated successfully!' : 'Your bid has been submitted successfully!';
            session()->flash('success', $msg);
            return redirect()->route('seller.agent.auction.detail', $this->auctionId);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            $this->activeTab = 0;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('[SellerBid] submit exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
        return view('livewire.seller.seller-agent-auction-bid', [
            'auctionId'     => $this->auctionId,
            'user_type'     => $this->user_type,
            'property_type' => $this->property_type,
        ])->extends('layouts.main')
            ->section('content');
    }
}
