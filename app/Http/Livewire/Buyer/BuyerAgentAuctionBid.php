<?php

namespace App\Http\Livewire\Buyer;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\BuyerAgentAuctionBid as BuyerAgentAuctionBidData;
use App\Models\AgentDefaultProfile;
use App\Services\AgentBidMapperService;
use App\Helpers\BuyerBidMatchScoreHelper;
use App\Models\BuyerAgentAuction;
use App\Models\User;
use App\Notifications\BidSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyerAgentAuctionBid extends Component
{
    use WithFileUploads;

    public $auctionId;
    public $editBidId = null;
    public $isEditMode = false;
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
    // public $flat_fee_services = [];
    public $total_flat_fee = 0;
    public $total_marketing_fee = 0;


    // Broker Compensation Properties
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
public $referral_fee_percent = '';
public $isListingCreatedByAgent = false;
public $brokerage_relationship = '';
public $additional_details_broker = '';
public $retained_deposits = '';




    // Presentation & Promotional Materials
    public $presentation_link;
    public $embedUrl;
    public $video_upload;
    public $business_card_link;
    public $business_card;
    public $business_card_stored_path = null;
    public $promo_material_type;
    public $promo_materials = [];
    public $promo_materials_link;

    public array $promoMaterials = [];
    public $purchase_fee_flat_type = '$';
    public string $lease_fee_flat_type = '$';
    public $gap_payment_type = '$';

        public $gap_payment_amount = '';
  public $down_payment_type = '$';
    public $down_payment_amount = '';
      public $seller_financing_type = '$';
    public $seller_financing_amount = '';
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
        if ($which === 'gap_payment_type') {
            $this->gap_payment_type = $type;
            $this->gap_payment_amount = ''; // clear lease input when switching type
        }
        if ($which === 'down_payment_type') {
            $this->down_payment_type = $type;
            $this->down_payment_amount = ''; // clear lease input when switching type
        }
        if ($which === 'seller_financing_type') {
            $this->seller_financing_type = $type;
            $this->seller_financing_amount = ''; // clear lease input when switching type
        }
    }

    

    protected function rules(): array
    {
        return [
            'bio'                 => 'required|string',
            'why_hire_you'        => 'required|string',
            'what_sets_you_apart' => 'required|string',
            'marketing_plan'      => 'required|string',
            'promoMaterials.*.type'  => ['nullable', 'string'],
            'promoMaterials.*.other' => ['nullable', 'string'],
            'promoMaterials.*.text'  => ['nullable', 'string'],
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
        $this->reviews_links[] = ['text' => '']; // Adds a new blank input field
    }

    public function removeReviewLink($index)
    {
        if (count($this->reviews_links) > 1) {
            array_splice($this->reviews_links, $index, 1); // Removes the selected entry
        }
    }

    public function updatedPurchaseFeeType()
    {
        $this->reset([
            'purchase_fee_flat',
            'purchase_fee_percentage',
            'purchase_fee_percentage_combo',
            'purchase_fee_flat_combo',
            'purchase_fee_other'
        ]);
    }

    public function updatedLeaseFeeType()
    {
        $this->reset([
            'lease_fee_flat',
            'lease_fee_percentage',
            'lease_fee_percentage_monthly_rent',
            'lease_fee_percentage_monthly_number',
            'lease_fee_flat_combo',
            'lease_fee_percentage_combo',
            'lease_fee_percentage_net',
            'lease_fee_flat_combo_net',
            'lease_fee_percentage_combo_net',
            'lease_fee_other'
        ]);
    }

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

        $this->compatibility_agent_response = [
            'communication_preferences'  => [],
            'negotiation_approach'       => [],
            'guidance_style'             => [],
            'collaboration_preferences'  => [],
            'transaction_strategy'       => [],
            'representation_philosophy'  => [],
            'representation_priorities'  => [],
        ];

        $auction = \App\Models\BuyerAgentAuction::find($auctionId);
        // $this->additional_details = $auction->get->additional_details ?? '';

        $this->service_type = $auction->get->service_type ?? '';
        $this->user_type = $auction->get->user_type ?? '';
        $this->property_type = $auction->get->property_type ?? '';

        $rawServices = is_string($auction->get->services) ? json_decode($auction->get->services, true) ?? [] : (array)$auction->get->services;
        $this->services = $this->filterServicesToCurrentCatalog(
            $this->normalizeBuyerServiceLabels($rawServices)
        );
        $this->other_services = is_string($auction->get->other_services) ? json_decode($auction->get->other_services, true) ?? [] : (array)$auction->get->other_services;

        $this->other_services_enabled = (bool)($auction->get->other_services_enabled ?? false);

        $this->auctionId = $auctionId;

        // Edit mode: read the bid ID from the URL ?edit= parameter
        $this->editBidId = request()->query('edit');
        $this->isEditMode = !empty($this->editBidId);

        // Initialize arrays
        $this->website_link = [''];
        $this->reviews_links = [['text' => '']];
        $this->social_media = [['platform' => '', 'text' => '']];

        // ── Compensation + overview prefill ───────────────────────────────────
        // Edit mode: load from the specific bid being edited.
        // New bid: load compensation prefill from the most recent bid by this agent,
        //          then overview/services come from the default profile below.
        $existingBid = $this->isEditMode
            ? BuyerAgentAuctionBidData::find($this->editBidId)
            : BuyerAgentAuctionBidData::where('buyer_agent_auction_id', $auctionId)
                ->where('user_id', Auth::id())
                ->latest()
                ->first();

        // Validate edit-mode ownership
        if ($this->isEditMode && $existingBid && $existingBid->user_id !== Auth::id()) {
            $existingBid = null;
            $this->isEditMode = false;
            $this->editBidId  = null;
        }

        if ($existingBid && $existingBid->get) {
            // Load every compensation field from the saved bid.
            // Cast to array so PHP 8.2 never raises "Undefined property" on stdClass.
            $b = (array) $existingBid->get;

            // In edit mode also load overview, services, and promo fields
            if ($this->isEditMode) {
                $this->bio                 = $b['bio'] ?? '';
                $this->why_hire_you        = $b['why_hire_you'] ?? '';
                $this->what_sets_you_apart = $b['what_sets_you_apart'] ?? '';
                $this->marketing_plan      = $b['marketing_plan'] ?? '';
                $this->year_licensed       = $b['year_licensed'] ?? '';
                $this->additional_details  = $b['additional_details'] ?? '';

                $rawReviews = $b['reviews_links'] ?? [];
                if (is_string($rawReviews)) $rawReviews = json_decode($rawReviews, true) ?? [];
                if (!empty($rawReviews)) $this->reviews_links = $rawReviews;

                $rawSocial = $b['social_media'] ?? [];
                if (is_string($rawSocial)) $rawSocial = json_decode($rawSocial, true) ?? [];
                if (!empty($rawSocial)) $this->social_media = $rawSocial;

                $wl = $b['website_link'] ?? '';
                $this->website_link = !empty($wl) ? (is_array($wl) ? $wl : [$wl]) : [''];

                $rawSvc = $b['services'] ?? [];
                if (is_string($rawSvc)) $rawSvc = json_decode($rawSvc, true) ?? [];
                if (!empty($rawSvc)) {
                    $this->services = $this->filterServicesToCurrentCatalog(
                        $this->normalizeBuyerServiceLabels((array) $rawSvc)
                    );
                }
                $rawOther = $b['other_services'] ?? [];
                if (is_string($rawOther)) $rawOther = json_decode($rawOther, true) ?? [];
                $this->other_services         = is_array($rawOther) ? $rawOther : [];
                $this->other_services_enabled = (bool) ($b['other_services_enabled'] ?? false);

                $rawPromo = $b['promoMaterials'] ?? [];
                if (is_string($rawPromo)) $rawPromo = json_decode($rawPromo, true) ?? [];
                if (!empty($rawPromo) && is_array($rawPromo)) $this->promoMaterials = $rawPromo;

                $this->presentation_link        = $b['presentation_link'] ?? null;
                $this->business_card_link       = $b['business_card_link'] ?? null;
                $this->business_card_stored_path = $b['business_card_stored_path'] ?? null;
                $this->awards_recognition        = $b['awards_recognition']        ?? '';
                $this->sold_listed_examples      = $b['sold_listed_examples']      ?? '';
                $this->marketing_success_examples = $b['marketing_success_examples'] ?? '';
                $this->review_1                  = trim($b['review_1']                  ?? '');
                $this->review_2                  = trim($b['review_2']                  ?? '');
                $this->review_3                  = trim($b['review_3']                  ?? '');
                // Fallback: if business_card meta key holds a stored file path, populate
                // business_card_stored_path so re-saves preserve it without re-uploading
                if (empty($this->business_card_stored_path) && !empty($b['business_card']) && is_string($b['business_card'])) {
                    $this->business_card_stored_path = $b['business_card'];
                }
                $this->additional_details_broker = $b['additional_details_broker'] ?? '';

                // Load agent credential fields from saved bid, falling back to user profile.
                // These must be set here (inside edit mode) so the profile auto-fill below
                // (which is guarded by !isEditMode) does not overwrite them.
                $_editUser = Auth::user();
                $this->first_name = (isset($b['first_name']) && trim($b['first_name']) !== '')
                    ? $b['first_name']
                    : ($_editUser->first_name ?? '');
                $this->last_name = (isset($b['last_name']) && trim($b['last_name']) !== '')
                    ? $b['last_name']
                    : ($_editUser->last_name ?? '');
                $this->phone = (isset($b['phone']) && trim($b['phone']) !== '')
                    ? $b['phone']
                    : ($_editUser->phone ?? '');
                $this->email = (isset($b['email']) && trim($b['email']) !== '')
                    ? $b['email']
                    : ($_editUser->email ?? '');
                $this->brokerage = (isset($b['brokerage']) && trim($b['brokerage']) !== '')
                    ? $b['brokerage']
                    : ($_editUser->brokerage ?? '');
                $this->license_no = (isset($b['license_no']) && trim($b['license_no']) !== '')
                    ? $b['license_no']
                    : ($_editUser->license_no ?? '');
                $this->nar_id = (isset($b['nar_id']) && trim($b['nar_id']) !== '')
                    ? $b['nar_id']
                    : ($_editUser->nar_id ?? '');
            }
            $this->avg_response_time          = $b['avg_response_time']          ?? '';
            $this->availability_status        = $b['availability_status']        ?? '';
            $this->evenings_available         = $b['evenings_available']         ?? '';
            $this->weekends_available         = $b['weekends_available']         ?? '';
            $this->years_experience           = $b['years_experience']           ?? '';
            $this->transactions_last_12_months = $b['transactions_last_12_months'] ?? '';
            $this->is_full_time               = $b['is_full_time']               ?? '';
            $this->primary_areas_served       = $b['primary_areas_served']       ?? '';
            $this->cities_served              = $b['cities_served']              ?? '';
            $this->counties_served            = $b['counties_served']            ?? '';
            $this->neighborhoods_served       = $b['neighborhoods_served']       ?? '';
            $this->areas_notes                = trim($b['areas_notes']                ?? '');
            $this->commission_structure                  = $b['commission_structure'] ?? '';
            $this->purchase_fee_type                     = $b['purchase_fee_type'] ?? '';
            $this->purchase_fee_flat                     = $b['purchase_fee_flat'] ?? '';
            $this->purchase_fee_percentage               = $b['purchase_fee_percentage'] ?? '';
            $this->purchase_fee_percentage_combo         = $b['purchase_fee_percentage_combo'] ?? '';
            $this->purchase_fee_flat_combo               = $b['purchase_fee_flat_combo'] ?? '';
            $this->purchase_fee_other                    = $b['purchase_fee_other'] ?? '';
            $this->interested_lease_option               = $b['interested_lease_option'] ?? '';
            $this->lease_fee_type                        = $b['lease_fee_type'] ?? '';
            $this->lease_fee_flat                        = $b['lease_fee_flat'] ?? '';
            $this->lease_fee_percentage                  = $b['lease_fee_percentage'] ?? '';
            $this->lease_fee_percentage_monthly_rent     = $b['lease_fee_percentage_monthly_rent'] ?? '';
            $this->lease_fee_percentage_monthly_number   = $b['lease_fee_percentage_monthly_number'] ?? '';
            $this->lease_fee_flat_combo                  = $b['lease_fee_flat_combo'] ?? '';
            $this->lease_fee_percentage_combo            = $b['lease_fee_percentage_combo'] ?? '';
            $this->lease_fee_percentage_net              = $b['lease_fee_percentage_net'] ?? '';
            $this->lease_fee_flat_combo_net              = $b['lease_fee_flat_combo_net'] ?? '';
            $this->lease_fee_percentage_combo_net        = $b['lease_fee_percentage_combo_net'] ?? '';
            $this->lease_fee_other                       = $b['lease_fee_other'] ?? '';
            $this->interested_lease_option_agreement     = $b['interested_lease_option_agreement'] ?? '';
            $this->lease_type                            = ($b['lease_type'] ?? '') ?: 'percent';
            $this->lease_value                           = $b['lease_value'] ?? '';
            $this->purchase_type                         = ($b['purchase_type'] ?? '') ?: 'percent';
            $this->purchase_value                        = $b['purchase_value'] ?? '';
            $this->protection_period                     = $b['protection_period'] ?? '';
            $this->early_termination_fee_option          = $b['early_termination_fee_option'] ?? '';
            $this->early_termination_fee_amount          = $b['early_termination_fee_amount'] ?? '';
            $this->retainer_fee_option                   = $b['retainer_fee_option'] ?? '';
            $this->retainer_fee_amount                   = $b['retainer_fee_amount'] ?? '';
            $this->retainer_fee_application              = $b['retainer_fee_application'] ?? '';
            $this->agency_agreement_timeframe            = $b['agency_agreement_timeframe'] ?? '';
            $this->agency_agreement_custom               = $b['agency_agreement_custom'] ?? '';
            $this->brokerage_relationship                = $b['brokerage_relationship'] ?? '';
            $this->additional_details_broker             = $b['additional_details_broker'] ?? '';
            $this->referral_fee_percent                  = $b['referral_fee_percent'] ?? '';

            // Compatibility preferences from saved bid
            $this->compatibility_agent_response = array_merge(
                $this->compatibility_agent_response,
                $existingBid->loadCompatibilityPreferences()
            );
        } else {
            // Load compensation defaults from the listing itself.
            // BuyerAgentAuction->get returns an anonymous class with __get(), NOT stdClass.
            // (array) cast mangles private property names — use direct -> access instead.
            $l = $auction->get;
            $this->commission_structure                  = $l->commission_structure ?? '';
            $this->purchase_fee_type                     = $l->purchase_fee_type ?? '';
            $this->purchase_fee_flat                     = $l->purchase_fee_flat ?? '';
            $this->purchase_fee_percentage               = $l->purchase_fee_percentage ?? '';
            $this->purchase_fee_percentage_combo         = $l->purchase_fee_percentage_combo ?? '';
            $this->purchase_fee_flat_combo               = $l->purchase_fee_flat_combo ?? '';
            $this->purchase_fee_other                    = $l->purchase_fee_other ?? '';
            $this->interested_lease_option               = $l->interested_lease_option ?? '';
            $this->lease_fee_type                        = $l->lease_fee_type ?? '';
            $this->lease_fee_flat                        = $l->lease_fee_flat ?? '';
            $this->lease_fee_percentage                  = $l->lease_fee_percentage ?? '';
            $this->lease_fee_percentage_monthly_rent     = $l->lease_fee_percentage_monthly_rent ?? '';
            $this->lease_fee_percentage_monthly_number   = $l->lease_fee_percentage_monthly_number ?? '';
            $this->lease_fee_flat_combo                  = $l->lease_fee_flat_combo ?? '';
            $this->lease_fee_percentage_combo            = $l->lease_fee_percentage_combo ?? '';
            $this->lease_fee_percentage_net              = $l->lease_fee_percentage_net ?? '';
            $this->lease_fee_flat_combo_net              = $l->lease_fee_flat_combo_net ?? '';
            $this->lease_fee_percentage_combo_net        = $l->lease_fee_percentage_combo_net ?? '';
            $this->lease_fee_other                       = $l->lease_fee_other ?? '';
            $this->interested_lease_option_agreement     = $l->interested_lease_option_agreement ?? '';
            $this->lease_type                            = ($l->lease_type ?? '') ?: 'percent';
            $this->lease_value                           = $l->lease_value ?? '';
            $this->purchase_type                         = ($l->purchase_type ?? '') ?: 'percent';
            $this->purchase_value                        = $l->purchase_value ?? '';
            $this->protection_period                     = $l->protection_period ?? '';
            $this->early_termination_fee_option          = $l->early_termination_fee_option ?? '';
            $this->early_termination_fee_amount          = $l->early_termination_fee_amount ?? '';
            $this->retainer_fee_option                   = $l->retainer_fee_option ?? '';
            $this->retainer_fee_amount                   = $l->retainer_fee_amount ?? '';
            $this->retainer_fee_application              = $l->retainer_fee_application ?? '';
            $this->agency_agreement_timeframe            = $l->agency_agreement_timeframe ?? '';
            $this->agency_agreement_custom               = $l->agency_agreement_custom ?? '';
            $this->brokerage_relationship                = $l->brokerage_relationship ?? '';
            $this->additional_details_broker             = $l->additional_details_broker ?? '';
            $this->referral_fee_percent                  = $l->referral_percentage ?? '';
        }
        $this->isListingCreatedByAgent = $auction->isCreatedByAgent();
        // ─────────────────────────────────────────────────────────────────────

        // Auto-fill Agent Information from user profile
        $user = Auth::user();
        if ($user) {
            // In edit mode, credential fields are loaded from the saved bid (above).
            // Only populate from user profile for new bids.
            if (!$this->isEditMode) {
                $this->first_name = $user->first_name ?? '';
                $this->last_name  = $user->last_name ?? '';
                $this->phone      = $user->phone ?? '';
                $this->email      = $user->email ?? '';
                $this->brokerage  = $user->brokerage ?? '';
                $this->license_no = $user->license_no ?? '';
                $this->nar_id     = $user->nar_id ?? '';
            }

            // Auto-load Default Profile only for new bids (not when editing an existing bid)
            if (!$this->isEditMode) {
                $profile = AgentDefaultProfile::findForAgentWithFallback(
                    $user->id,
                    'buyer',
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
                    // Broker Compensation fields from preset
                    $this->applyPresetField('commission_structure', $mapped['commission_structure'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_type', $mapped['purchase_fee_type'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_flat', $mapped['purchase_fee_flat'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_percentage', $mapped['purchase_fee_percentage'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_percentage_combo', $mapped['purchase_fee_percentage_combo'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_flat_combo', $mapped['purchase_fee_flat_combo'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('purchase_fee_other', $mapped['purchase_fee_other'] ?? null, $presetFieldsApplied);
                    $this->applyPresetField('interested_lease_option', $mapped['interested_lease_option'] ?? null, $presetFieldsApplied);
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
                                'role'                  => 'buyer',
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
        }
    }

    private function normalizeBuyerServiceLabels(array $services): array
    {
        return array_map(function ($s) {
            return str_replace("\x27", "\u{2019}", str_replace("\x5c\x27", "\u{2019}", $s));
        }, $services);
    }

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

        AgentDefaultProfile::upsertForAgent($user->id, 'buyer', $propType, $data);

        if (!AgentDefaultProfile::findRoleDefault($user->id, 'buyer')) {
            AgentDefaultProfile::upsertRoleDefault($user->id, 'buyer', $data);
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
        AgentDefaultProfile::upsertRoleDefault($user->id, 'buyer', $this->buildProfileData());

        $this->defaultProfileExists = true;
        $this->defaultProfileLoaded = true;
        session()->flash('success', 'Saved as your main Buyer default profile — it will pre-fill all property types that don\'t have their own override.');
    }

    public function loadDefaultProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $profile = AgentDefaultProfile::findForAgentWithFallback($user->id, 'buyer', $this->property_type ?: 'residential');
        if (!$profile) return;

        $data = $profile->profile_data ?? [];
        $this->bio                 = $data['bio'] ?? '';
        $this->why_hire_you        = $data['why_hire_you'] ?? '';
        $this->what_sets_you_apart = $data['what_sets_you_apart'] ?? '';
        $this->marketing_plan      = $data['marketing_plan'] ?? '';
        $rawRl = $data['reviews_links'] ?? [['text' => '']];
        $this->reviews_links = array_values(array_map(function ($item) {
            if (is_object($item)) $item = (array) $item;
            if (!is_array($item)) return ['text' => ''];
            return ['text' => (!empty($item['text']) ? $item['text'] : ($item['url'] ?? ''))];
        }, $rawRl ?: [['text' => '']]));
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
            return $videoId
                ? "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&playsinline=1"
                : null;
        }
        if (str_contains($url, 'vimeo.com')) {
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)(?:[\/?#]|$)/i', $url, $m);
            $videoId = $m[1] ?? null;
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
        \Log::info('[BuyerBid] submit() started', [
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
            \Log::info('[BuyerBid] validation passed');

            // BYA-H6: an expired listing no longer accepts NEW bids. Editing an
            // already-placed bid is unaffected. Mirrors the accept/reject expiry
            // guards already enforced in the bid controllers (status === 'Expired'
            // is derived from the listing's expiration_date).
            if (!($this->isEditMode && $this->editBidId)) {
                $listingForExpiry = BuyerAgentAuction::find($this->auctionId);
                if ($listingForExpiry && $listingForExpiry->status === 'Expired') {
                    DB::rollBack();
                    session()->flash('error', 'This listing has expired and is no longer accepting new bids.');
                    return redirect()->route('buyer.view-auction', $this->auctionId);
                }
            }

            $allowedVideos = ['mp4', 'mov', 'avi'];
            $allowedPhotos = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];

            if ($this->isEditMode && $this->editBidId) {
                $bid = BuyerAgentAuctionBidData::find($this->editBidId);
                if (!$bid || $bid->user_id !== Auth::id()) {
                    session()->flash('error', 'You cannot edit this bid.');
                    return;
                }
                if ($bid->accepted === 'accepted' || $bid->accepted === 'rejected') {
                    session()->flash('error', 'Cannot edit a bid that has been accepted or rejected.');
                    return;
                }
            } else {
                $bid = new BuyerAgentAuctionBidData();
                $bid->user_id = Auth::id();
                $bid->buyer_agent_auction_id = $this->auctionId;
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
            $bid->saveMeta('awards_recognition',        $this->awards_recognition);
            $bid->saveMeta('sold_listed_examples',      $this->sold_listed_examples);
            $bid->saveMeta('marketing_success_examples', $this->marketing_success_examples);
            $bid->saveMeta('review_1',                  trim($this->review_1));
            $bid->saveMeta('review_2',                  trim($this->review_2));
            $bid->saveMeta('review_3',                  trim($this->review_3));

            // Save Services
            $bid->saveMeta('services', json_encode($this->services));
            $bid->saveMeta('other_services', json_encode($this->other_services ?? null));
            // $bid->saveMeta('flat_fee_services', json_encode($this->flat_fee_services));

            $bid->saveMeta('other_services_enabled', $this->other_services_enabled);

            $bid->saveMeta('additional_details', $this->additional_details);

                        // Save method for Broker Compensation
                $bid->saveMeta('commission_structure', $this->commission_structure);

                // Purchase Fee
                $bid->saveMeta('purchase_fee_type', $this->purchase_fee_type);
                $bid->saveMeta('purchase_fee_flat', $this->purchase_fee_flat);
                $bid->saveMeta('purchase_fee_percentage', $this->purchase_fee_percentage);
                $bid->saveMeta('purchase_fee_percentage_combo', $this->purchase_fee_percentage_combo);
                $bid->saveMeta('purchase_fee_flat_combo', $this->purchase_fee_flat_combo);
                $bid->saveMeta('purchase_fee_other', $this->purchase_fee_other);

                // Lease Agreement Interest
                $bid->saveMeta('interested_lease_option', $this->interested_lease_option);

                // Lease Fee (only if interested_lease_option is 'Yes')
                if ($this->interested_lease_option === 'Yes') {
                    $bid->saveMeta('lease_fee_type', $this->lease_fee_type);
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
                }

                // Lease-Option Agreement Interest
                $bid->saveMeta('interested_lease_option_agreement', $this->interested_lease_option_agreement);

                // Lease-Option Agreement Compensation (only if interested_lease_option_agreement is 'Yes')
                if ($this->interested_lease_option_agreement === 'Yes') {
                    $bid->saveMeta('lease_type', $this->lease_type);
                    $bid->saveMeta('lease_value', str_replace(',', '', $this->lease_value ?? ''));
                    $bid->saveMeta('purchase_type', $this->purchase_type);
                    $bid->saveMeta('purchase_value', str_replace(',', '', $this->purchase_value ?? ''));
                }

            // Other Broker Terms
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
            $buyerAuction = \App\Models\BuyerAgentAuction::find($this->auctionId);
            if ($buyerAuction && $buyerAuction->isCreatedByAgent()) {
                $bid->saveMeta('referral_fee_percent', $this->referral_fee_percent);
            }

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
            if ($this->business_card && is_object($this->business_card) && method_exists($this->business_card, 'getClientOriginalExtension')) {
                $extension = $this->business_card->getClientOriginalExtension();
                if (in_array($extension, $allowedPhotos)) {
                    $uuid = (string) Str::uuid();
                    $fileName = $uuid . '.' . $extension;
                    $path = $this->business_card->storeAs('auction/documents', $fileName, 'public');
                    $bid->saveMeta('business_card', $path);
                }
            } elseif (!empty($this->business_card_stored_path)) {
                // No new file uploaded — persist the pre-saved / previously stored path
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
                            if (is_string($file) && !empty($file)) {
                                // Existing stored path — preserve it as-is
                                $stored[] = $file;
                                continue;
                            }
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

            DB::commit();

            // Notify the listing owner (buyer) that a new bid was submitted
            try {
                $auction = BuyerAgentAuction::find($this->auctionId);
                if ($auction) {
                    $listingOwner = User::find($auction->user_id);
                    if ($listingOwner) {
                        $listingOwner->notify(new BidSubmittedNotification($bid, $auction, 'buyer_agent'));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send bid submitted notification for buyer agent listing', [
                    'bid_id'     => $bid->id ?? null,
                    'auction_id' => $this->auctionId,
                    'error'      => $e->getMessage(),
                ]);
            }

            session()->flash('success', 'Your bid has been submitted successfully!');

            return redirect()->route('buyer.view-auction', $this->auctionId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::warning('[BuyerBid] ValidationException caught', ['errors' => $e->errors()]);
            $this->activeTab = 0;
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[BuyerBid] Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', 'Error saving auction: ' . $e->getMessage());
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
        return view('livewire.buyer.buyer-agent-auction-bid', [
            'auctionId' => $this->auctionId, // explicitly pass if you like
            'user_type' => $this->user_type, // explicitly pass if you like
            'property_type' => $this->property_type, // explicitly pass if you like
        ])->extends('layouts.main')
            ->section('content');
    }
}
