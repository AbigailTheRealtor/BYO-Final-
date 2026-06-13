<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Services\AgentPresetCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AgentPresetController extends Controller
{
    /**
     * Public profile fields that are subject to the save-scope selector.
     * These are the profile_data keys that describe the agent's public identity.
     * Services, compensation, and agreement term keys are intentionally excluded.
     */
    protected const PROFILE_FIELDS = [
        'bio',
        'why_hire_you',
        'what_sets_you_apart',
        'marketing_plan',
        'additional_details',
        'year_licensed',
        'first_name',
        'last_name',
        'phone',
        'email',
        'brokerage',
        'license_no',
        'nar_id',
        'brokerage_relationship',
        'presentation_link',
        'presentation_upload_path',
        'business_card_link',
        'business_card_upload_path',
        'reviews_links',
        'website_link',
        'social_media',
        'years_experience',
        'transactions_last_12_months',
        'primary_areas_served',
        'avg_response_time',
        'is_full_time',
        'cities_served',
        'counties_served',
        'neighborhoods_served',
        'areas_notes',
        'review_1',
        'review_2',
        'review_3',
        'awards_recognition',
        'intro_video_url',
        'video_caption',
        'availability_status',
        'evenings_available',
        'weekends_available',
        'communication_style',
        'preferred_contact_method',
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (Auth::user()->user_type !== 'agent') {
                abort(403, 'Only agents may access preset management.');
            }
            return $next($request);
        });
    }

    /**
     * Show the preset management index — a card grid for all role/property combos.
     */
    public function index()
    {
        $userId = Auth::id();

        $roles = AgentPresetCatalog::getRoles();

        $presets = [];
        foreach ($roles as $role) {
            foreach (AgentPresetCatalog::getPropertyTypes($role) as $propertyType) {
                $profile     = AgentDefaultProfile::findForAgent($userId, $role, $propertyType);
                $profileData = $profile?->profile_data ?? [];
                $presets[$role][$propertyType] = [
                    'exists'     => $profile !== null,
                    'services'   => count($profileData['services'] ?? []),
                    'has_bio'    => !empty($profileData['bio']),
                    'has_creds'  => !empty($profileData['first_name']),
                    'updated_at' => $profile?->updated_at,
                    'coaching'   => \App\Services\RecommendationService::presetCompletionAnalysis($profileData, $role),
                ];
            }
        }

        $agentShortId = Auth::user()->short_id;

        return view('agent-presets.index', compact('presets', 'roles', 'agentShortId'));
    }

    /**
     * Show the edit form for a specific role + property type preset.
     */
    public function edit(string $role, string $propertyType)
    {
        if (!AgentPresetCatalog::isValidCombination($role, $propertyType)) {
            abort(404);
        }

        $userId   = Auth::id();
        $profile  = AgentDefaultProfile::findForAgent($userId, $role, $propertyType);
        $data     = $profile?->profile_data ?? [];
        $services = AgentPresetCatalog::getServices($role, $propertyType);

        $selectedServices = $data['services'] ?? [];
        $otherServices    = $data['other_services'] ?? [];

        $roleLabel     = AgentDefaultProfile::roleLabel($role);
        $propertyLabel = AgentDefaultProfile::propertyLabel($propertyType);
        $profileExists = $profile !== null;
        $agentShortId  = Auth::user()->short_id;
        $hireMeUrl     = route('hire.agent.public', [
            'agentShortId' => $agentShortId,
            'role'         => $role,
            'propertyType' => $propertyType,
        ]);

        return view('agent-presets.edit', compact(
            'role', 'propertyType', 'data', 'services',
            'selectedServices', 'otherServices', 'roleLabel', 'propertyLabel',
            'profileExists', 'agentShortId', 'hireMeUrl'
        ));
    }

    /**
     * Save (create or update) a preset for the given role + property type.
     */
    public function save(Request $request, string $role, string $propertyType)
    {
        if (!AgentPresetCatalog::isValidCombination($role, $propertyType)) {
            abort(404);
        }

        $request->validate([
            'profile_save_scope' => ['nullable', 'string', 'in:current_preset,current_role,all_roles'],
            'services'          => ['nullable', 'array'],
            'services.*'        => ['string', 'max:500'],
            'other_services'    => ['nullable', 'array'],
            'other_services.*'  => ['string', 'max:500'],
            'bio'               => ['nullable', 'string', 'max:5000'],
            'why_hire_you'      => ['nullable', 'string', 'max:5000'],
            'what_sets_you_apart' => ['nullable', 'string', 'max:5000'],
            'marketing_plan'    => ['nullable', 'string', 'max:5000'],
            'additional_details'=> ['nullable', 'string', 'max:5000'],
            'year_licensed'     => ['nullable', 'string', 'max:50'],
            'first_name'        => ['nullable', 'string', 'max:100'],
            'last_name'         => ['nullable', 'string', 'max:100'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:200'],
            'brokerage'         => ['nullable', 'string', 'max:200'],
            'license_no'        => ['nullable', 'string', 'max:100'],
            'nar_id'            => ['nullable', 'string', 'max:100'],
            'presentation_link'   => ['nullable', 'url', 'max:500'],
            'presentation_upload' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,ppt,pptx', 'max:10240'],
            'business_card_link'  => ['nullable', 'url', 'max:500'],
            'business_card_upload'=> ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,ppt,pptx', 'max:10240'],
            'reviews_links_raw' => ['nullable', 'string', 'max:5000'],
            'website_link_raw'  => ['nullable', 'string', 'max:5000'],
            'social_media_raw'  => ['nullable', 'string', 'max:5000'],
            // Broker Compensation & Agency Agreement Terms
            'protection_period'                      => ['nullable', 'string', 'max:20'],
            'early_termination_fee_option'           => ['nullable', 'string', 'max:10'],
            'early_termination_fee_amount'           => ['nullable', 'string', 'max:50'],
            'agency_agreement_timeframe'             => ['nullable', 'string', 'max:50'],
            'agency_agreement_custom'                => ['nullable', 'string', 'max:200'],
            'interested_lease_option_agreement'      => ['nullable', 'string', 'max:10'],
            'lease_type'                             => ['nullable', 'string', 'max:20'],
            'lease_value'                            => ['nullable', 'string', 'max:50'],
            'purchase_type'                          => ['nullable', 'string', 'max:20'],
            'purchase_value'                         => ['nullable', 'string', 'max:50'],
            'commission_structure'                   => ['nullable', 'string', 'max:200'],
            'purchase_fee_type'                      => ['nullable', 'string', 'max:100'],
            'purchase_fee_flat'                      => ['nullable', 'string', 'max:50'],
            'purchase_fee_percentage'                => ['nullable', 'string', 'max:20'],
            'purchase_fee_percentage_combo'          => ['nullable', 'string', 'max:20'],
            'purchase_fee_flat_combo'                => ['nullable', 'string', 'max:50'],
            'purchase_fee_other'                     => ['nullable', 'string', 'max:500'],
            'retainer_fee_option'                    => ['nullable', 'string', 'max:10'],
            'retainer_fee_amount'                    => ['nullable', 'string', 'max:50'],
            'retainer_fee_application'               => ['nullable', 'string', 'max:100'],
            'interested_lease_option'                => ['nullable', 'string', 'max:10'],
            'lease_fee_type'                         => ['nullable', 'string', 'max:100'],
            'lease_fee_flat'                         => ['nullable', 'string', 'max:50'],
            'lease_fee_percentage'                   => ['nullable', 'string', 'max:20'],
            'lease_fee_percentage_monthly_rent'      => ['nullable', 'string', 'max:20'],
            'lease_fee_percentage_monthly_number'    => ['nullable', 'string', 'max:20'],
            'lease_fee_flat_combo'                   => ['nullable', 'string', 'max:50'],
            'lease_fee_percentage_combo'             => ['nullable', 'string', 'max:20'],
            'lease_fee_percentage_net'               => ['nullable', 'string', 'max:20'],
            'lease_fee_flat_combo_net'               => ['nullable', 'string', 'max:50'],
            'lease_fee_percentage_combo_net'         => ['nullable', 'string', 'max:20'],
            'lease_fee_other'                        => ['nullable', 'string', 'max:500'],
            'nominal'                                => ['nullable', 'string', 'max:50'],
            'commission_structure_type'              => ['nullable', 'string', 'max:100'],
            'commission_structure_type_fee_flat'     => ['nullable', 'string', 'max:50'],
            'commission_structure_type_fee_flat_combo'       => ['nullable', 'string', 'max:50'],
            'commission_structure_type_fee_percentage'       => ['nullable', 'string', 'max:20'],
            'commission_structure_type_fee_percentage_combo' => ['nullable', 'string', 'max:20'],
            'commission_structure_type_fee_other'            => ['nullable', 'string', 'max:500'],
            'interested_purchase_fee_type'           => ['nullable', 'string', 'max:10'],
            'seller_leasing_fee_type'                => ['nullable', 'string', 'max:100'],
            'seller_leasing_gross'                   => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_rental'            => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_month_rent'        => ['nullable', 'string', 'max:50'],
            'sales_tax_option_gross'                 => ['nullable', 'string', 'max:20'],
            'purchase_fee_rental_period'             => ['nullable', 'string', 'max:20'],
            'purchase_fee_net_aggregate'             => ['nullable', 'string', 'max:20'],
            'purchase_fee_gross_rent'                => ['nullable', 'string', 'max:20'],
            'purchase_fee_monthly_percentage'        => ['nullable', 'string', 'max:20'],
            'purchase_fee_months'                    => ['nullable', 'string', 'max:20'],
            'sales_tax_option_monthly'               => ['nullable', 'string', 'max:20'],
            'purchase_fee_flat_commercial'           => ['nullable', 'string', 'max:50'],
            'sales_tax_option_flat'                  => ['nullable', 'string', 'max:20'],
            'purchase_fee_other_commercial'          => ['nullable', 'string', 'max:500'],
            'tenant_broker_commission_structure'     => ['nullable', 'string', 'max:200'],
            'tenant_broker_fee_structure'            => ['nullable', 'string', 'max:100'],
            'tenant_broker_percentage'               => ['nullable', 'string', 'max:20'],
            'tenant_broker_gross_lease'              => ['nullable', 'string', 'max:20'],
            'tenant_broker_first_month_rent'         => ['nullable', 'string', 'max:20'],
            'tenant_broker_flat_fee'                 => ['nullable', 'string', 'max:50'],
            'tenant_broker_other'                    => ['nullable', 'string', 'max:500'],
            'broker_fee_timing'                      => ['nullable', 'string', 'max:200'],
            'broker_fee_days_from_rent'              => ['nullable', 'string', 'max:20'],
            'broker_fee_days_after_lease'            => ['nullable', 'string', 'max:20'],
            'broker_fee_days_after_rent'             => ['nullable', 'string', 'max:20'],
            'broker_fee_timing_other'                => ['nullable', 'string', 'max:500'],
            'renewal_fee_type'                       => ['nullable', 'string', 'max:100'],
            'renewal_fee_percentage'                 => ['nullable', 'string', 'max:20'],
            'renewal_fee_lease_value'                => ['nullable', 'string', 'max:20'],
            'renewal_fee_first_month'                => ['nullable', 'string', 'max:20'],
            'renewal_fee_flat_fee'                   => ['nullable', 'string', 'max:50'],
            'renewal_fee_custom'                     => ['nullable', 'string', 'max:500'],
            'renewal_fee_sales_tax_lease_value'      => ['nullable', 'string', 'max:20'],
            'renewal_fee_no_of_months'               => ['nullable', 'string', 'max:20'],
            'renewal_fee_sales_tax_first_month'      => ['nullable', 'string', 'max:20'],
            'renewal_fee_sales_tax_flat_fee'         => ['nullable', 'string', 'max:20'],
            'expansion_commission_percentage'        => ['nullable', 'string', 'max:20'],
            'interested_in_property_management'                      => ['nullable', 'string', 'max:10'],
            'interested_in_property_management_fee'                  => ['nullable', 'string', 'max:100'],
            'interested_in_property_management_fee_gross_lease'      => ['nullable', 'string', 'max:20'],
            'interested_in_property_management_fee_rental_periord'   => ['nullable', 'string', 'max:20'],
            'interested_in_property_management_fee_flate_free'       => ['nullable', 'string', 'max:50'],
            'interested_in_property_management_fee_other'            => ['nullable', 'string', 'max:500'],
            'interested_in_selling'                  => ['nullable', 'string', 'max:10'],
            'interested_in_selling_type'             => ['nullable', 'string', 'max:100'],
            'landlord_broker_purchase_price'         => ['nullable', 'string', 'max:20'],
            'landlord_broker_percentage_price'       => ['nullable', 'string', 'max:20'],
            'landlord_broker_dollar_price'           => ['nullable', 'string', 'max:50'],
            'landlord_broker_flate_fee'              => ['nullable', 'string', 'max:50'],
            'landlord_broker_other'                  => ['nullable', 'string', 'max:500'],
            'purchase_fee_purchase_price'            => ['nullable', 'string', 'max:20'],
            'brokerage_relationship'                 => ['nullable', 'string', 'max:100'],
            'additional_details_broker'              => ['nullable', 'string', 'max:5000'],
            'retained_deposits'                      => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_other'                    => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_percentage'              => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_purchase_fee_flat_amount'=> ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_purchase_fee_other'      => ['nullable', 'string', 'max:500'],
            'seller_leasing_each_rental'             => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_no_of_months'      => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_flat_combo'        => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_percentage_combo'  => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_flat_net_combo'    => ['nullable', 'string', 'max:50'],
            'seller_leasing_gross_percentage_net_combo' => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_sales_tax_first_month'     => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_sales_tax_option_gross'    => ['nullable', 'string', 'max:20'],
            'seller_leasing_gross_sales_tax_flat_free_gross' => ['nullable', 'string', 'max:20'],
            'referral_fee_percent'                           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'split_payment_due'                              => ['nullable', 'string', 'max:255'],
            'split_payment_due_other'                        => ['nullable', 'string', 'max:255'],
            // Profile-only display fields
            'years_experience'              => ['nullable', 'string', 'max:50'],
            'transactions_last_12_months'   => ['nullable', 'numeric', 'integer', 'min:0'],
            'primary_areas_served'          => ['nullable', 'string', 'max:500'],
            'avg_response_time'             => ['nullable', 'string', 'max:100'],
            'is_full_time'                  => ['nullable', 'string', 'max:10'],
            'cities_served'                 => ['nullable', 'string', 'max:1000'],
            'counties_served'               => ['nullable', 'string', 'max:1000'],
            'neighborhoods_served'          => ['nullable', 'string', 'max:1000'],
            'areas_notes'                   => ['nullable', 'string', 'max:2000'],
            'review_1'                      => ['nullable', 'string', 'max:2000'],
            'review_2'                      => ['nullable', 'string', 'max:2000'],
            'review_3'                      => ['nullable', 'string', 'max:2000'],
            'awards_recognition'            => ['nullable', 'string', 'max:2000'],
            'intro_video_url'               => ['nullable', 'url', 'max:500'],
            'video_caption'                 => ['nullable', 'string', 'max:500'],
            'availability_status'           => ['nullable', 'string', 'max:100'],
            'evenings_available'            => ['nullable', 'string', 'max:10'],
            'weekends_available'            => ['nullable', 'string', 'max:10'],
            'communication_style'           => ['nullable', 'string', 'max:500'],
            'preferred_contact_method'      => ['nullable', 'string', 'max:100'],
        ]);

        $otherServicesRaw = $request->input('other_services', []);
        $otherServices    = array_values(array_filter(
            array_map('trim', (array) $otherServicesRaw)
        ));

        $userId = Auth::id();

        // Retrieve any existing record so we can carry over stored upload paths
        // when no new file is submitted for that slot.
        $existingProfile = AgentDefaultProfile::findForAgent($userId, $role, $propertyType);
        $existingData    = $existingProfile?->profile_data ?? [];

        // Handle Presentation Upload
        $presentationUploadPath = $existingData['presentation_upload_path'] ?? null;
        if ($request->hasFile('presentation_upload') && $request->file('presentation_upload')->isValid()) {
            $file = $request->file('presentation_upload');
            $dir  = 'agent-offer-presets/' . $userId;
            $name = 'presentation_' . \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
            $presentationUploadPath = Storage::disk('public')->putFileAs($dir, $file, $name);
        }

        // Handle Business Card / Headshot Upload
        $businessCardUploadPath = $existingData['business_card_upload_path'] ?? null;
        if ($request->hasFile('business_card_upload') && $request->file('business_card_upload')->isValid()) {
            $file = $request->file('business_card_upload');
            $dir  = 'agent-offer-presets/' . $userId;
            $name = 'business_card_' . \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
            $businessCardUploadPath = Storage::disk('public')->putFileAs($dir, $file, $name);
        }

        $profileData = [
            'services'            => $request->input('services', []),
            'other_services'      => $otherServices,
            'bio'                 => $request->input('bio', ''),
            'why_hire_you'        => $request->input('why_hire_you', ''),
            'what_sets_you_apart' => $request->input('what_sets_you_apart', ''),
            'marketing_plan'      => $request->input('marketing_plan', ''),
            'additional_details'  => $request->input('additional_details', ''),
            'year_licensed'       => $request->input('year_licensed', ''),
            'first_name'          => $request->input('first_name', ''),
            'last_name'           => $request->input('last_name', ''),
            'phone'               => $request->input('phone', ''),
            'email'               => $request->input('email', ''),
            'brokerage'           => $request->input('brokerage', ''),
            'license_no'          => $request->input('license_no', ''),
            'nar_id'              => $request->input('nar_id', ''),
            'presentation_link'        => $request->input('presentation_link', ''),
            'presentation_upload_path' => $presentationUploadPath,
            'business_card_link'       => $request->input('business_card_link', ''),
            'business_card_upload_path'=> $businessCardUploadPath,
            'reviews_links'       => static::splitLines($request->input('reviews_links_raw', '')),
            'website_link'        => static::splitLines($request->input('website_link_raw', '')),
            'social_media'        => static::splitLines($request->input('social_media_raw', '')),
            // Broker Compensation & Agency Agreement Terms
            'protection_period'                      => $request->input('protection_period', ''),
            'early_termination_fee_option'           => $request->input('early_termination_fee_option', ''),
            'early_termination_fee_amount'           => $request->input('early_termination_fee_amount', ''),
            'agency_agreement_timeframe'             => $request->input('agency_agreement_timeframe', ''),
            'agency_agreement_custom'                => $request->input('agency_agreement_custom', ''),
            'interested_lease_option_agreement'      => $request->input('interested_lease_option_agreement', ''),
            'lease_type'                             => $request->input('lease_type', ''),
            'lease_value'                            => $request->input('lease_value', ''),
            'purchase_type'                          => $request->input('purchase_type', ''),
            'purchase_value'                         => $request->input('purchase_value', ''),
            'commission_structure'                   => $request->input('commission_structure', ''),
            'purchase_fee_type'                      => $request->input('purchase_fee_type', ''),
            'purchase_fee_flat'                      => $request->input('purchase_fee_flat', ''),
            'purchase_fee_percentage'                => $request->input('purchase_fee_percentage', ''),
            'purchase_fee_percentage_combo'          => $request->input('purchase_fee_percentage_combo', ''),
            'purchase_fee_flat_combo'                => $request->input('purchase_fee_flat_combo', ''),
            'purchase_fee_other'                     => $request->input('purchase_fee_other', ''),
            'retainer_fee_option'                    => $request->input('retainer_fee_option', ''),
            'retainer_fee_amount'                    => $request->input('retainer_fee_amount', ''),
            'retainer_fee_application'               => $request->input('retainer_fee_application', ''),
            'interested_lease_option'                => $request->input('interested_lease_option', ''),
            'lease_fee_type'                         => $request->input('lease_fee_type', ''),
            'lease_fee_flat'                         => $request->input('lease_fee_flat', ''),
            'lease_fee_percentage'                   => $request->input('lease_fee_percentage', ''),
            'lease_fee_percentage_monthly_rent'      => $request->input('lease_fee_percentage_monthly_rent', ''),
            'lease_fee_percentage_monthly_number'    => $request->input('lease_fee_percentage_monthly_number', ''),
            'lease_fee_flat_combo'                   => $request->input('lease_fee_flat_combo', ''),
            'lease_fee_percentage_combo'             => $request->input('lease_fee_percentage_combo', ''),
            'lease_fee_percentage_net'               => $request->input('lease_fee_percentage_net', ''),
            'lease_fee_flat_combo_net'               => $request->input('lease_fee_flat_combo_net', ''),
            'lease_fee_percentage_combo_net'         => $request->input('lease_fee_percentage_combo_net', ''),
            'lease_fee_other'                        => $request->input('lease_fee_other', ''),
            'nominal'                                => $request->input('nominal', ''),
            'commission_structure_type'              => $request->input('commission_structure_type', ''),
            'commission_structure_type_fee_flat'     => $request->input('commission_structure_type_fee_flat', ''),
            'commission_structure_type_fee_flat_combo'       => $request->input('commission_structure_type_fee_flat_combo', ''),
            'commission_structure_type_fee_percentage'       => $request->input('commission_structure_type_fee_percentage', ''),
            'commission_structure_type_fee_percentage_combo' => $request->input('commission_structure_type_fee_percentage_combo', ''),
            'commission_structure_type_fee_other'            => $request->input('commission_structure_type_fee_other', ''),
            'interested_purchase_fee_type'           => $request->input('interested_purchase_fee_type', ''),
            'seller_leasing_fee_type'                => $request->input('seller_leasing_fee_type', ''),
            'seller_leasing_gross'                   => $request->input('seller_leasing_gross', ''),
            'seller_leasing_gross_rental'            => $request->input('seller_leasing_gross_rental', ''),
            'seller_leasing_gross_month_rent'        => $request->input('seller_leasing_gross_month_rent', ''),
            'sales_tax_option_gross'                 => $request->input('sales_tax_option_gross', ''),
            'purchase_fee_rental_period'             => $request->input('purchase_fee_rental_period', ''),
            'purchase_fee_net_aggregate'             => $request->input('purchase_fee_net_aggregate', ''),
            'purchase_fee_gross_rent'                => $request->input('purchase_fee_gross_rent', ''),
            'purchase_fee_monthly_percentage'        => $request->input('purchase_fee_monthly_percentage', ''),
            'purchase_fee_months'                    => $request->input('purchase_fee_months', ''),
            'sales_tax_option_monthly'               => $request->input('sales_tax_option_monthly', ''),
            'purchase_fee_flat_commercial'           => $request->input('purchase_fee_flat_commercial', ''),
            'sales_tax_option_flat'                  => $request->input('sales_tax_option_flat', ''),
            'purchase_fee_other_commercial'          => $request->input('purchase_fee_other_commercial', ''),
            'tenant_broker_commission_structure'     => $request->input('tenant_broker_commission_structure', ''),
            'tenant_broker_fee_structure'            => $request->input('tenant_broker_fee_structure', ''),
            'tenant_broker_percentage'               => $request->input('tenant_broker_percentage', ''),
            'tenant_broker_gross_lease'              => $request->input('tenant_broker_gross_lease', ''),
            'tenant_broker_first_month_rent'         => $request->input('tenant_broker_first_month_rent', ''),
            'tenant_broker_flat_fee'                 => $request->input('tenant_broker_flat_fee', ''),
            'tenant_broker_other'                    => $request->input('tenant_broker_other', ''),
            'broker_fee_timing'                      => $request->input('broker_fee_timing', ''),
            'broker_fee_days_from_rent'              => $request->input('broker_fee_days_from_rent', ''),
            'broker_fee_days_after_lease'            => $request->input('broker_fee_days_after_lease', ''),
            'broker_fee_days_after_rent'             => $request->input('broker_fee_days_after_rent', ''),
            'broker_fee_timing_other'                => $request->input('broker_fee_timing_other', ''),
            'renewal_fee_type'                       => $request->input('renewal_fee_type', ''),
            'renewal_fee_percentage'                 => $request->input('renewal_fee_percentage', ''),
            'renewal_fee_lease_value'                => $request->input('renewal_fee_lease_value', ''),
            'renewal_fee_first_month'                => $request->input('renewal_fee_first_month', ''),
            'renewal_fee_flat_fee'                   => $request->input('renewal_fee_flat_fee', ''),
            'renewal_fee_custom'                     => $request->input('renewal_fee_custom', ''),
            'renewal_fee_sales_tax_lease_value'      => $request->input('renewal_fee_sales_tax_lease_value', ''),
            'renewal_fee_no_of_months'               => $request->input('renewal_fee_no_of_months', ''),
            'renewal_fee_sales_tax_first_month'      => $request->input('renewal_fee_sales_tax_first_month', ''),
            'renewal_fee_sales_tax_flat_fee'         => $request->input('renewal_fee_sales_tax_flat_fee', ''),
            'expansion_commission_percentage'        => $request->input('expansion_commission_percentage', ''),
            'interested_in_property_management'                      => $request->input('interested_in_property_management', ''),
            'interested_in_property_management_fee'                  => $request->input('interested_in_property_management_fee', ''),
            'interested_in_property_management_fee_gross_lease'      => $request->input('interested_in_property_management_fee_gross_lease', ''),
            'interested_in_property_management_fee_rental_periord'   => $request->input('interested_in_property_management_fee_rental_periord', ''),
            'interested_in_property_management_fee_flate_free'       => $request->input('interested_in_property_management_fee_flate_free', ''),
            'interested_in_property_management_fee_other'            => $request->input('interested_in_property_management_fee_other', ''),
            'interested_in_selling'                  => $request->input('interested_in_selling', ''),
            'interested_in_selling_type'             => $request->input('interested_in_selling_type', ''),
            'landlord_broker_purchase_price'         => $request->input('landlord_broker_purchase_price', ''),
            'landlord_broker_percentage_price'       => $request->input('landlord_broker_percentage_price', ''),
            'landlord_broker_dollar_price'           => $request->input('landlord_broker_dollar_price', ''),
            'landlord_broker_flate_fee'              => $request->input('landlord_broker_flate_fee', ''),
            'landlord_broker_other'                  => $request->input('landlord_broker_other', ''),
            'purchase_fee_purchase_price'            => $request->input('purchase_fee_purchase_price', ''),
            'brokerage_relationship'                 => $request->input('brokerage_relationship', ''),
            'additional_details_broker'              => $request->input('additional_details_broker', ''),
            'retained_deposits'                      => $request->input('retained_deposits', ''),
            'seller_leasing_gross_other'                    => $request->input('seller_leasing_gross_other', ''),
            'seller_leasing_gross_percentage'              => $request->input('seller_leasing_gross_percentage', ''),
            'seller_leasing_gross_purchase_fee_flat_amount'=> $request->input('seller_leasing_gross_purchase_fee_flat_amount', ''),
            'seller_leasing_gross_purchase_fee_other'      => $request->input('seller_leasing_gross_purchase_fee_other', ''),
            'seller_leasing_each_rental'             => $request->input('seller_leasing_each_rental', ''),
            'seller_leasing_gross_no_of_months'      => $request->input('seller_leasing_gross_no_of_months', ''),
            'seller_leasing_gross_flat_combo'        => $request->input('seller_leasing_gross_flat_combo', ''),
            'seller_leasing_gross_percentage_combo'  => $request->input('seller_leasing_gross_percentage_combo', ''),
            'seller_leasing_gross_flat_net_combo'    => $request->input('seller_leasing_gross_flat_net_combo', ''),
            'seller_leasing_gross_percentage_net_combo' => $request->input('seller_leasing_gross_percentage_net_combo', ''),
            'seller_leasing_gross_sales_tax_first_month'     => $request->input('seller_leasing_gross_sales_tax_first_month', ''),
            'seller_leasing_gross_sales_tax_option_gross'    => $request->input('seller_leasing_gross_sales_tax_option_gross', ''),
            'seller_leasing_gross_sales_tax_flat_free_gross' => $request->input('seller_leasing_gross_sales_tax_flat_free_gross', ''),
            'referral_fee_percent'                           => $request->input('referral_fee_percent', ''),
            'split_payment_due'                              => $request->input('split_payment_due', ''),
            'split_payment_due_other'                        => $request->input('split_payment_due_other', ''),
            // Profile-only display fields
            'years_experience'              => $request->input('years_experience', ''),
            'transactions_last_12_months'   => $request->filled('transactions_last_12_months') ? (int) $request->input('transactions_last_12_months') : null,
            'primary_areas_served'          => $request->input('primary_areas_served', ''),
            'avg_response_time'             => $request->input('avg_response_time', ''),
            'is_full_time'                  => $request->input('is_full_time', ''),
            'cities_served'                 => $request->input('cities_served', ''),
            'counties_served'               => $request->input('counties_served', ''),
            'neighborhoods_served'          => $request->input('neighborhoods_served', ''),
            'areas_notes'                   => $request->input('areas_notes', ''),
            'review_1'                      => $request->input('review_1', ''),
            'review_2'                      => $request->input('review_2', ''),
            'review_3'                      => $request->input('review_3', ''),
            'awards_recognition'            => $request->input('awards_recognition', ''),
            'intro_video_url'               => $request->input('intro_video_url', ''),
            'video_caption'                 => $request->input('video_caption', ''),
            'availability_status'           => $request->input('availability_status', ''),
            'evenings_available'            => $request->input('evenings_available', ''),
            'weekends_available'            => $request->input('weekends_available', ''),
            'communication_style'           => $request->input('communication_style', ''),
            'preferred_contact_method'      => $request->input('preferred_contact_method', ''),
        ];

        AgentDefaultProfile::upsertForAgent($userId, $role, $propertyType, $profileData);

        // Scope-aware propagation to other presets.
        //
        // 'current_preset' (default): saves only to the current role/property-type
        //   record — no propagation to other presets.
        //
        // 'current_role': propagates ALL profile_data fields (except per-preset file-
        //   upload paths) to every other property-type preset for the same role.
        //   e.g. saving "All Buyer presets" writes compensation, services, agreement
        //   terms, and profile fields to buyer/income, buyer/commercial, etc.
        //   File-upload paths (presentation_upload_path, business_card_upload_path) are
        //   excluded because each preset manages its own uploaded files independently.
        //   Cross-role isolation is enforced: only records where role_type === $role
        //   are ever updated.
        //
        // 'all_roles': propagates only PROFILE_FIELDS (safe public-profile keys) across
        //   all roles and property types. Compensation, services, and agreement-term
        //   fields are intentionally excluded until a cross-role compensation field
        //   mapping audit confirms that those fields are safe to propagate universally.
        $scope = $request->input('profile_save_scope', 'current_preset');

        // Keys that must never be propagated by any scope selector — each preset manages
        // its own uploaded file paths independently. Note: these keys also appear in
        // PROFILE_FIELDS but must be excluded from all scope propagation.
        $fileUploadKeys = ['presentation_upload_path', 'business_card_upload_path'];

        if ($scope === 'current_role' || $scope === 'all_roles') {
            if ($scope === 'current_role') {
                // Build propagation set from keys that were actually present in the HTTP
                // request, not from the full $profileData map.
                //
                // $profileData is constructed with request->input($key, '') for every
                // known field, so absent keys default to ''. Relying on $profileData alone
                // would propagate empty strings for property-type-specific fields that do
                // not appear in the source form's HTML, silently blanking out target-preset
                // values that were set for a different context.
                //
                // Using request key presence instead gives the correct semantics:
                //   - A field submitted as '' (agent intentionally cleared it) propagates.
                //   - A field absent from the HTTP body (not in the form HTML) does not
                //     propagate and leaves the target preset's value intact.
                //   - File-upload path keys are always excluded regardless.
                //
                // Values are taken from $profileData (not raw request data) so that any
                // controller-side normalisation (splitLines, array casting, etc.) is kept.
                //
                // Three form inputs use a different name from their stored profile_data key
                // because the controller transforms their values via splitLines():
                //   reviews_links_raw  → reviews_links
                //   website_link_raw   → website_link
                //   social_media_raw   → social_media
                // These must be explicitly mapped so they are included in propagation.
                // File-upload inputs (presentation_upload, business_card_upload) map to
                // the excluded _path keys and are handled by $fileUploadKeys below.
                $rawKeyToProfileKey = [
                    'reviews_links_raw' => 'reviews_links',
                    'website_link_raw'  => 'website_link',
                    'social_media_raw'  => 'social_media',
                ];
                $submittedProfileKeys = [];
                foreach (array_keys($request->all()) as $requestKey) {
                    $storedKey = $rawKeyToProfileKey[$requestKey] ?? $requestKey;
                    if (array_key_exists($storedKey, $profileData)) {
                        $submittedProfileKeys[] = $storedKey;
                    }
                }
                $propagateFields = array_diff_key(
                    array_intersect_key($profileData, array_flip($submittedProfileKeys)),
                    array_flip($fileUploadKeys)
                );
            } else {
                // all_roles: only safe public-profile keys until cross-role compensation
                // audit is complete — do not expand this list without that audit.
                // File-upload paths are explicitly stripped even though they appear in
                // PROFILE_FIELDS, because each preset manages its own uploaded files.
                $propagateFields = array_diff_key(
                    array_intersect_key($profileData, array_flip(static::PROFILE_FIELDS)),
                    array_flip($fileUploadKeys)
                );
            }

            if ($scope === 'current_role') {
                // Iterate over ALL known property types for this role and upsert each one.
                // This ensures missing presets are created rather than silently skipped.
                // Cross-role isolation is maintained: only records where role_type === $role
                // are ever written.
                $allPropertyTypes = AgentPresetCatalog::getPropertyTypes($role);
                foreach ($allPropertyTypes as $targetPropertyType) {
                    if ($targetPropertyType === $propertyType) {
                        // Already saved as the primary preset above — skip.
                        continue;
                    }
                    $targetProfile = AgentDefaultProfile::findForAgent($userId, $role, $targetPropertyType);
                    $existing = $targetProfile?->profile_data ?? [];
                    foreach ($propagateFields as $field => $value) {
                        $existing[$field] = $value;
                    }
                    AgentDefaultProfile::upsertForAgent($userId, $role, $targetPropertyType, $existing);
                }
            } else {
                // all_roles: update only existing presets (leave as-is per task scope).
                $query = AgentDefaultProfile::where('user_id', $userId)
                    ->where(function ($q) use ($role, $propertyType) {
                        $q->where('role_type', '!=', $role)
                          ->orWhere('property_type', '!=', $propertyType);
                    });

                $otherPresets = $query->get();

                foreach ($otherPresets as $otherPreset) {
                    $existing = $otherPreset->profile_data ?? [];
                    foreach ($propagateFields as $field => $value) {
                        $existing[$field] = $value;
                    }
                    $otherPreset->profile_data = $existing;
                    $otherPreset->save();
                }
            }
        }

        return redirect()
            ->route('agent.presets.edit', ['role' => $role, 'propertyType' => $propertyType, 'saved' => 1, 'scope' => $scope]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
        ]);

        $user = Auth::user();

        $file = $request->file('avatar');

        // Derive a safe extension from the server-detected MIME type only.
        // Never trust the client-supplied filename extension.
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $mime      = $file->getMimeType();
        $extension = $mimeToExt[$mime] ?? null;

        if ($extension === null) {
            return back()->withErrors(['avatar' => 'Unsupported image format. Please upload a JPEG, PNG, GIF, or WebP file.']);
        }

        $filename = 'agent_' . $user->id . '_' . \Illuminate\Support\Str::uuid() . '.' . $extension;
        $destDir  = public_path('images/avatar');

        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $oldAvatar = $user->avatar;

        $file->move($destDir, $filename);

        // Persist the new avatar first; only delete the old file after a successful save
        // so a save failure never leaves the database pointing at a deleted file.
        $user->avatar = $filename;
        $user->save();

        // Match all known app-generated avatar filename formats (whitelist):
        //   DashboardController  → {uuid}.{ext}
        //   AgentPresetController (new) → agent_{id}_{uuid}.{ext}
        //   AgentPresetController (legacy) → agent_{id}_{timestamp}.{ext}
        $uploadedAvatarPattern = '/^(agent_\d+_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|\d{9,11})|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.(jpg|jpeg|png|gif|webp)$/i';

        if (
            $oldAvatar
            && $oldAvatar !== $filename
            && preg_match($uploadedAvatarPattern, $oldAvatar)
        ) {
            $oldPath = $destDir . DIRECTORY_SEPARATOR . basename($oldAvatar);
            if (file_exists($oldPath) && !unlink($oldPath)) {
                \Illuminate\Support\Facades\Log::warning('Failed to delete old avatar file', ['path' => $oldPath]);
            }
        }

        return redirect()
            ->route('agent.presets.index')
            ->with('success', 'Profile photo updated successfully.');
    }

    protected static function splitLines(?string $value): array
    {
        if (empty($value)) {
            return [];
        }
        return array_values(array_filter(
            array_map('trim', explode("\n", str_replace("\r", '', $value)))
        ));
    }
}
