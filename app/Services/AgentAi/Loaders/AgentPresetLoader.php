<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\AgentDefaultProfile;

/**
 * AgentPresetLoader
 *
 * Loads the agent's preset service profiles from `agent_default_profiles` —
 * all roles and property types — returning a summary of each preset's public
 * service offerings, approach, and availability.
 *
 * Registration:
 *   source_key: 'agent_presets'
 *   priority:   70
 *   scopes:     all five AgentAiContextScope values
 *
 * GOVERNANCE:
 *   - Specific fee amounts, commission percentages, and dollar rates are NEVER
 *     included. Only fee structure *type* and *description* labels are exposed.
 *   - Private contact fields (email, phone) are NEVER included.
 *   - Entire `profile_data` array is filtered through the private-key exclusion
 *     list before any key reaches the fragment content.
 *   - No DB writes. No external HTTP calls.
 */
class AgentPresetLoader
{
    use LoaderHelpers;

    public const SOURCE_KEY = 'agent_presets';
    public const PRIORITY   = 70;
    public const CACHE_TTL  = 1800;

    /**
     * Keys that are private and must never appear in the chat context.
     * Per audit Section 7.1 and Section 12.1.
     */
    private const PRIVATE_KEYS = [
        'email',
        'phone',
        'business_card_upload_path',
        'business_card_link',
        'purchase_fee_percentage',
        'purchase_fee_flat',
        'lease_fee_percentage',
        'lease_fee_flat',
        'retainer_fee_amount',
        'referral_fee_percent',
        'early_termination_fee_amount',
        'nominal',
        'first_name',
        'last_name',
        'bio',
        'awards_recognition',
        'what_sets_you_apart',
        'why_hire_you',
        'review_1',
        'review_2',
        'review_3',
        'reviews_links',
        'intro_video_url',
        'website_link',
        'presentation_link',
        'social_media',
        'marketing_plan',
        'cities_served',
        'counties_served',
        'neighborhoods_served',
        'primary_areas_served',
        'areas_notes',
        'license_no',
        'nar_id',
        'year_licensed',
        'years_experience',
        'is_full_time',
        'transactions_last_12_months',
        'availability_status',
        'avg_response_time',
        'communication_style',
        'preferred_contact_method',
        'evenings_available',
        'weekends_available',
    ];

    /**
     * Callable entry point registered with AgentAiContextSourceRegistry.
     *
     * @param  array $scopeContext  {scope, agent_id, listing_type, listing_id}
     * @return array|null           Fragment or null when no presets found.
     */
    public function __invoke(array $scopeContext): ?array
    {
        $agentId = (int) ($scopeContext['agent_id'] ?? 0);
        if ($agentId <= 0) {
            return null;
        }

        $presets = AgentDefaultProfile::where('user_id', $agentId)
            ->orderBy('role_type')
            ->orderBy('property_type')
            ->get();

        if ($presets->isEmpty()) {
            return null;
        }

        $summaries = [];
        foreach ($presets as $preset) {
            $summary = $this->summarizePreset($preset);
            if (!empty($summary)) {
                $summaries[] = $summary;
            }
        }

        if (empty($summaries)) {
            return null;
        }

        return self::makeFragment(
            self::SOURCE_KEY,
            self::PRIORITY,
            ['presets' => $summaries, 'preset_count' => count($summaries)],
            true,
            ['seller', 'landlord', 'buyer', 'tenant', 'agent_profile'],
            self::CACHE_TTL
        );
    }

    /**
     * Summarize a single preset record into its public-safe content.
     *
     * Only includes keys that are meaningful for service-level context.
     * All compensation amount/rate keys are excluded.
     *
     * @param  AgentDefaultProfile $preset
     * @return array
     */
    private function summarizePreset(AgentDefaultProfile $preset): array
    {
        $data = (array) ($preset->profile_data ?? []);

        $get = fn (string $key) => $data[$key] ?? null;

        $services = $get('services');
        if (is_array($services)) {
            $services = implode(', ', array_filter($services));
        }
        $otherServices = $get('other_services');
        if (is_array($otherServices)) {
            $otherServices = implode(', ', array_filter($otherServices));
        }

        $roleLabel     = AgentDefaultProfile::roleLabel($preset->role_type);
        $propertyLabel = AgentDefaultProfile::propertyLabel($preset->property_type);

        $summary = array_filter([
            'role'                          => $roleLabel,
            'property_type'                 => $propertyLabel,
            'services'                      => $services ?: null,
            'other_services'                => $otherServices ?: null,
            'commission_structure'          => $get('commission_structure'),
            'commission_structure_type'     => $get('commission_structure_type'),
            'purchase_fee_type'             => $get('purchase_fee_type'),
            'lease_fee_type'                => $get('lease_fee_type'),
            'retainer_fee_option'           => $get('retainer_fee_option'),
            'retainer_fee_application'      => $get('retainer_fee_application'),
            'protection_period'             => $get('protection_period'),
            'early_termination_fee_option'  => $get('early_termination_fee_option'),
            'interested_in_selling'         => $this->boolLabel($get('interested_in_selling')),
            'interested_in_property_management' => $this->boolLabel($get('interested_in_property_management')),
            'interested_in_property_management_fee' => $get('interested_in_property_management_fee'),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        foreach (self::PRIVATE_KEYS as $key) {
            unset($summary[$key]);
        }

        foreach (array_keys($summary) as $key) {
            if (
                str_ends_with($key, '_flat_fee')
                || str_ends_with($key, '_percentage')
                || str_ends_with($key, '_price')
                || (str_ends_with($key, '_amount') && !in_array($key, [
                    'retainer_fee_application',
                ], true))
            ) {
                unset($summary[$key]);
            }
        }

        return $summary;
    }

    /**
     * Convert a boolean/truthy value to a human-readable 'Yes'/'No' label.
     *
     * @param  mixed $value
     * @return string|null
     */
    private function boolLabel($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 'Yes' : 'No';
    }
}
