<?php

namespace App\Http\Livewire\Seller;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid as SellerAgentAuctionBidData;
use App\Models\AgentDefaultProfile;
use Illuminate\Support\Facades\DB;

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
        ];
    }

    protected array $messages = [
        'bio.required'                 => 'Please fill in "About Agent".',
        'why_hire_you.required'        => 'Please fill in "Why Should You Be Hired as Their Agent?".',
        'what_sets_you_apart.required' => 'Please fill in "What Sets You Apart From Other Agents?".',
        'marketing_plan.required'      => 'Please fill in "What Is Your Marketing Strategy?".',
        'year_licensed.required'       => 'Please enter the year you were licensed.',
    ];

    public function mount($auctionId = null)
    {
        $this->promoMaterials = [
            ['type' => '', 'other' => '', 'link' => '', 'files' => []],
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

        // Services start empty — agent selects only what they will offer
        // (do not pre-fill from listing to avoid inflating the services count)

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

        // Initialize arrays
        $this->website_link  = [''];
        $this->reviews_links = [['text' => '']];
        $this->social_media  = [['platform' => '', 'text' => '']];

        // Auto-fill agent info and default profile
        $user = Auth::user();
        if ($user) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name  = $user->last_name ?? '';
            $this->phone      = $user->phone ?? '';
            $this->email      = $user->email ?? '';
            $this->brokerage  = $user->brokerage ?? '';
            $this->license_no = $user->license_no ?? '';
            $this->nar_id     = $user->nar_id ?? '';

            $defaultProfile = AgentDefaultProfile::findForAgentWithFallback(
                $user->id,
                'seller',
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
                if (!empty($dp['reviews_links'])) {
                    $this->reviews_links = $dp['reviews_links'];
                }
                if (!empty($dp['website_link'])) {
                    $this->website_link = $dp['website_link'];
                }
                if (!empty($dp['social_media'])) {
                    $this->social_media = $dp['social_media'];
                }
                if (!empty($dp['additional_details'])) {
                    $this->additional_details = $dp['additional_details'];
                }
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

        if ($this->activeTab < 5) {
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

        $profile = AgentDefaultProfile::findForAgentWithFallback($user->id, 'seller', $this->property_type ?: 'residential');
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

    public function submit()
    {
        DB::beginTransaction();
        try {
            $this->validate();

            $bid = new SellerAgentAuctionBidData();
            $bid->user_id = Auth::id();
            $bid->seller_agent_auction_id = $this->auctionId;
            $bid->save();

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

            DB::commit();
            session()->flash('success', 'Your bid has been submitted successfully!');
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
