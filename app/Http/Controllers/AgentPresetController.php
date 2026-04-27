<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Services\AgentPresetCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentPresetController extends Controller
{
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
                $profile = AgentDefaultProfile::findForAgent($userId, $role, $propertyType);
                $presets[$role][$propertyType] = [
                    'exists'     => $profile !== null,
                    'services'   => count($profile?->profile_data['services'] ?? []),
                    'has_bio'    => !empty($profile?->profile_data['bio']),
                    'has_creds'  => !empty($profile?->profile_data['first_name']),
                    'updated_at' => $profile?->updated_at,
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

        $profile  = AgentDefaultProfile::findForAgent(Auth::id(), $role, $propertyType);
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
            'presentation_link' => ['nullable', 'url', 'max:500'],
            'business_card_link'=> ['nullable', 'url', 'max:500'],
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
            'renewal_fee_flat_free'                  => ['nullable', 'string', 'max:50'],
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
        ]);

        $otherServicesRaw = $request->input('other_services', []);
        $otherServices    = array_values(array_filter(
            array_map('trim', (array) $otherServicesRaw)
        ));

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
            'presentation_link'   => $request->input('presentation_link', ''),
            'business_card_link'  => $request->input('business_card_link', ''),
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
            'renewal_fee_flat_free'                  => $request->input('renewal_fee_flat_free', ''),
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
        ];

        AgentDefaultProfile::upsertForAgent(Auth::id(), $role, $propertyType, $profileData);

        return redirect()
            ->route('agent.presets.edit', ['role' => $role, 'propertyType' => $propertyType, 'saved' => 1]);
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
