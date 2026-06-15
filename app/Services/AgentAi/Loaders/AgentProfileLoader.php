<?php

namespace App\Services\AgentAi\Loaders;

use App\Models\AgentDefaultProfile;
use App\Models\User;

/**
 * AgentProfileLoader
 *
 * Loads the agent's public profile — name, brokerage, bio, service areas,
 * specialties, credentials, license number, and availability — from the
 * `users` table and `agent_default_profiles`.
 *
 * Registration:
 *   source_key: 'agent_profile'
 *   priority:   80
 *   scopes:     all five AgentAiContextScope values
 *
 * GOVERNANCE:
 *   - Email and phone are NEVER included (private per audit Section 7).
 *   - Specific fee amounts and commission rates are NEVER included.
 *   - Only public-safe keys from Section 7.1 are exposed.
 *   - No DB writes. No external HTTP calls.
 */
class AgentProfileLoader
{
    use LoaderHelpers;

    public const SOURCE_KEY = 'agent_profile';
    public const PRIORITY   = 80;
    public const CACHE_TTL  = 1800;

    /**
     * Private profile_data keys that must NEVER appear in chat context.
     * Per audit Section 7.1 — Compensation Structure (Conditionally Private) and
     * Contact (Private).
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
    ];

    /**
     * Callable entry point registered with AgentAiContextSourceRegistry.
     *
     * @param  array $scopeContext  {scope, agent_id, listing_type, listing_id}
     * @return array|null           Fragment or null when agent not found.
     */
    public function __invoke(array $scopeContext): ?array
    {
        $agentId = (int) ($scopeContext['agent_id'] ?? 0);
        if ($agentId <= 0) {
            return null;
        }

        $user = User::find($agentId);
        if (!$user) {
            return null;
        }

        $profileData = $this->loadBestProfileData($agentId, $scopeContext);

        $content = $this->buildContent($user, $profileData);

        return self::makeFragment(
            self::SOURCE_KEY,
            self::PRIORITY,
            $content,
            true,
            ['seller', 'landlord', 'buyer', 'tenant', 'agent_profile'],
            self::CACHE_TTL
        );
    }

    /**
     * Load the most relevant profile_data for this agent.
     *
     * Priority: listing-type-specific preset → role default → first available preset.
     *
     * @param  int   $agentId
     * @param  array $scopeContext
     * @return array
     */
    private function loadBestProfileData(int $agentId, array $scopeContext): array
    {
        $listingType = $scopeContext['listing_type'] ?? null;

        if ($listingType) {
            $preset = AgentDefaultProfile::findRoleDefault($agentId, $listingType);
            if ($preset && !empty($preset->profile_data)) {
                return (array) $preset->profile_data;
            }
        }

        $anyPreset = AgentDefaultProfile::where('user_id', $agentId)
            ->whereNotNull('profile_data')
            ->orderBy('updated_at', 'desc')
            ->first();

        return $anyPreset ? (array) ($anyPreset->profile_data ?? []) : [];
    }

    /**
     * Build the public-safe content array.
     *
     * @param  User  $user
     * @param  array $profileData  profile_data from agent_default_profiles
     * @return array
     */
    private function buildContent(User $user, array $profileData): array
    {
        $get = fn (string $key) => $profileData[$key] ?? null;

        $firstName = $get('first_name') ?? $user->first_name ?? null;
        $lastName  = $get('last_name')  ?? $user->last_name  ?? null;

        $isPrivateKey = fn (string $key): bool => in_array($key, self::PRIVATE_KEYS, true)
            || str_ends_with($key, '_flat_fee')
            || str_ends_with($key, '_percentage')
            || str_ends_with($key, '_price')
            || (str_ends_with($key, '_amount') && !in_array($key, [
                'retainer_fee_application', 'protection_period',
            ], true));

        $services = $get('services');
        if (is_array($services)) {
            $services = implode(', ', array_filter($services));
        }
        $otherServices = $get('other_services');
        if (is_array($otherServices)) {
            $otherServices = implode(', ', array_filter($otherServices));
        }

        $socialMedia = $get('social_media');
        if (is_array($socialMedia)) {
            $socialMedia = implode(', ', array_filter(array_values($socialMedia)));
        }

        return [
            'agent_name'                   => trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: null,
            'short_id'                     => $user->short_id ?? null,
            'brokerage'                    => $get('brokerage') ?? ($user->getBrokerageAttribute(null) ?? null),
            'license_no'                   => $get('license_no'),
            'nar_id'                       => $get('nar_id'),
            'year_licensed'                => $get('year_licensed'),
            'years_experience'             => $get('years_experience'),
            'is_full_time'                 => $get('is_full_time') !== null ? ($get('is_full_time') ? 'Yes' : 'No') : null,
            'transactions_last_12_months'  => $get('transactions_last_12_months'),
            'bio'                          => self::truncateText($get('bio') !== null ? (string) $get('bio') : null),
            'awards_recognition'           => self::truncateText($get('awards_recognition') !== null ? (string) $get('awards_recognition') : null),
            'what_sets_you_apart'          => self::truncateText($get('what_sets_you_apart') !== null ? (string) $get('what_sets_you_apart') : null),
            'why_hire_you'                 => self::truncateText($get('why_hire_you') !== null ? (string) $get('why_hire_you') : null),
            'review_1'                     => self::truncateText($get('review_1') !== null ? (string) $get('review_1') : null),
            'review_2'                     => self::truncateText($get('review_2') !== null ? (string) $get('review_2') : null),
            'review_3'                     => self::truncateText($get('review_3') !== null ? (string) $get('review_3') : null),
            'reviews_links'                => $get('reviews_links'),
            'website_link'                 => $get('website_link'),
            'intro_video_url'              => $get('intro_video_url'),
            'presentation_link'            => $get('presentation_link'),
            'social_media'                 => $socialMedia,
            'availability_status'          => $get('availability_status'),
            'avg_response_time'            => $get('avg_response_time'),
            'communication_style'          => $get('communication_style'),
            'preferred_contact_method'     => $get('preferred_contact_method'),
            'evenings_available'           => $get('evenings_available') !== null ? ($get('evenings_available') ? 'Yes' : 'No') : null,
            'weekends_available'           => $get('weekends_available') !== null ? ($get('weekends_available') ? 'Yes' : 'No') : null,
            'cities_served'                => is_array($get('cities_served'))
                ? implode(', ', array_filter($get('cities_served')))
                : (is_string($get('cities_served')) ? $get('cities_served') : null),
            'counties_served'              => is_array($get('counties_served'))
                ? implode(', ', array_filter($get('counties_served')))
                : (is_string($get('counties_served')) ? $get('counties_served') : null),
            'neighborhoods_served'         => is_array($get('neighborhoods_served'))
                ? implode(', ', array_filter($get('neighborhoods_served')))
                : (is_string($get('neighborhoods_served')) ? $get('neighborhoods_served') : null),
            'primary_areas_served'         => is_array($get('primary_areas_served'))
                ? implode(', ', array_filter($get('primary_areas_served')))
                : (is_string($get('primary_areas_served')) ? $get('primary_areas_served') : null),
            'areas_notes'                  => self::truncateText($get('areas_notes') !== null ? (string) $get('areas_notes') : null),
            'services'                     => $services,
            'other_services'               => $otherServices,
            'marketing_plan'               => self::truncateText($get('marketing_plan') !== null ? (string) $get('marketing_plan') : null),
            'commission_structure'         => $get('commission_structure'),
            'commission_structure_type'    => $get('commission_structure_type'),
            'purchase_fee_type'            => $get('purchase_fee_type'),
            'lease_fee_type'               => $get('lease_fee_type'),
            'retainer_fee_option'          => $get('retainer_fee_option'),
            'retainer_fee_application'     => $get('retainer_fee_application'),
            'protection_period'            => $get('protection_period'),
            'early_termination_fee_option' => $get('early_termination_fee_option'),
            'interested_in_selling'        => $get('interested_in_selling') !== null ? ($get('interested_in_selling') ? 'Yes' : 'No') : null,
            'interested_in_selling_type'   => $get('interested_in_selling_type'),
            'interested_in_property_management' => $get('interested_in_property_management') !== null
                ? ($get('interested_in_property_management') ? 'Yes' : 'No')
                : null,
            'interested_in_property_management_fee' => $get('interested_in_property_management_fee'),
        ];
    }
}
