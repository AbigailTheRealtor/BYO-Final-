<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentBidMapperService;
use App\Services\AgentPresetCatalog;
use App\Support\ServicesFormatter;
use Illuminate\Support\Facades\Auth;

class AgentProfileController extends Controller
{
    /**
     * Keys from profile_data that are safe to expose on the public profile page (35 keys).
     * Only these keys are passed to the Blade view — all other keys (compensation
     * structures, referral fees, agency agreement terms, broker fee details, etc.)
     * are stripped server-side before the view is rendered.
     */
    public const PUBLIC_PROFILE_KEYS = [
        // Services (shown as read-only bullet list on public profile)
        'services',
        'other_services',
        // Identity
        'first_name',
        'last_name',
        'brokerage',
        // Bio / pitch
        'bio',
        'why_hire_you',
        'what_sets_you_apart',
        // Credentials
        'license_no',
        'nar_id',
        'year_licensed',
        'brokerage_relationship',
        // Quick highlights
        'years_experience',
        'transactions_last_12_months',
        'avg_response_time',
        'is_full_time',
        // Areas served
        'primary_areas_served',
        'cities_served',
        'counties_served',
        'neighborhoods_served',
        'areas_notes',
        // Social proof
        'review_1',
        'review_2',
        'review_3',
        'awards_recognition',
        // Video intro
        'intro_video_url',
        'video_caption',
        // Presentation & links
        'presentation_link',
        'business_card_link',
        'website_link',
        'social_media',
        'reviews_links',
        // Availability & service style
        'availability_status',
        'evenings_available',
        'weekends_available',
        'communication_style',
        'preferred_contact_method',
        // Marketing plan (visible to all)
        'marketing_plan',
        // Additional notes (visible to all)
        'additional_details',
    ];

    public function show(string $agentShortId)
    {
        $agent = User::where('short_id', $agentShortId)
            ->where('user_type', 'agent')
            ->firstOrFail();

        $isOwnerPreview = Auth::check() && Auth::id() === $agent->id;

        $profiles = AgentDefaultProfile::where('user_id', $agent->id)->get();

        // Build hire button data per role. For each role, collect ALL property
        // types that have a valid preset with at least one service configured.
        $hireButtons = [];
        foreach (AgentPresetCatalog::getRoles() as $role) {
            $validOptions = [];
            foreach (AgentPresetCatalog::getPropertyTypes($role) as $propertyType) {
                $profile = $profiles->first(function ($p) use ($role, $propertyType) {
                    return $p->role_type === $role && $p->property_type === $propertyType;
                });
                if (!$profile) {
                    continue;
                }
                $services = HireAgentDirectController::resolveServices($profile);
                if (count($services) > 0) {
                    $validOptions[] = [
                        'propertyType' => $propertyType,
                        'propLabel'    => AgentDefaultProfile::propertyLabel($propertyType),
                        'url'          => route('hire.agent.public', [
                            'agentShortId' => $agentShortId,
                            'role'         => $role,
                            'propertyType' => $propertyType,
                        ]),
                    ];
                }
            }
            if (count($validOptions) > 0) {
                $hireButtons[] = [
                    'role'      => $role,
                    'roleLabel' => AgentDefaultProfile::roleLabel($role),
                    'options'   => $validOptions,
                    'direct'    => count($validOptions) === 1,
                ];
            }
        }

        // Select a stable primary profile using a fixed priority order so that
        // saving any preset last does not change the public identity.
        $hasProfileContent = function ($p) {
            $data = $p->profile_data ?? [];
            foreach (self::PUBLIC_PROFILE_KEYS as $key) {
                $val = $data[$key] ?? null;
                if ($val !== null && $val !== '' && $val !== []) {
                    return true;
                }
            }
            return false;
        };

        $priorityOrder = [
            ['buyer',    'residential'],
            ['seller',   'residential'],
            ['landlord', 'residential'],
            ['tenant',   'residential'],
        ];

        $primaryProfile = null;
        foreach ($priorityOrder as [$r, $pt]) {
            $candidate = $profiles->first(fn($p) => $p->role_type === $r && $p->property_type === $pt);
            if ($candidate && $hasProfileContent($candidate)) {
                $primaryProfile = $candidate;
                break;
            }
        }

        if ($primaryProfile === null) {
            $primaryProfile = $profiles->first(fn($p) => $hasProfileContent($p))
                ?? $profiles->sortBy('id')->first()
                ?? null;
        }
        $data = $primaryProfile?->profile_data ?? [];

        // Build compensation data for authenticated viewers only (before whitelist strip).
        $compensationFields = [
            'commission_structure', 'purchase_fee_type', 'purchase_fee_flat',
            'purchase_fee_percentage', 'lease_fee_type', 'lease_fee_flat',
            'lease_fee_percentage', 'broker_fee_timing', 'agency_agreement_timeframe',
            'agency_agreement_custom', 'protection_period', 'brokerage_relationship',
            'early_termination_fee_option', 'early_termination_fee_amount',
            'retainer_fee_option', 'retainer_fee_amount', 'retainer_fee_application',
            'additional_details_broker', 'retained_deposits', 'renewal_fee_type',
            'expansion_commission_percentage', 'lease_option_fee_type',
            'lease_option_fee_flat', 'lease_option_fee_percentage',
        ];
        $compensationData = [];
        if (Auth::check()) {
            foreach ($compensationFields as $field) {
                if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                    $compensationData[$field] = $data[$field];
                }
            }
        }

        // Extract compatibility data before the whitelist strip so the view can
        // render a "Working Style & Compatibility" section from the structured sections.
        $compatibilityData = AgentBidMapperService::mapCompatibilityFromProfile($data);

        // Strip every key that is not on the public-safe whitelist so that
        // private compensation/fee fields are never present in the view,
        // regardless of what any future template edit might reference.
        $data = array_intersect_key($data, array_flip(self::PUBLIC_PROFILE_KEYS));

        // Normalize array-typed link fields against legacy string storage.
        foreach (['website_link', 'social_media', 'reviews_links'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = array_values(array_filter(
                    array_map('trim', explode("\n", str_replace("\r", '', $data[$key])))
                ));
            } elseif (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = [];
            }
        }

        // Build grouped standard-services list for read-only display on the profile page.
        // Use the primary profile's role+property context to get catalog order.
        $groupedProfileServices = [];
        if ($primaryProfile && !empty($data['services']) && is_array($data['services'])) {
            $flowKey = $primaryProfile->role_type . '_agent.' . $primaryProfile->property_type;
            $groupedProfileServices = ServicesFormatter::orderSelectedServices(
                $data['services'],
                $flowKey
            );
        }

        return view('agent-profile.show', compact(
            'agent',
            'data',
            'hireButtons',
            'isOwnerPreview',
            'agentShortId',
            'groupedProfileServices',
            'compensationData',
            'compatibilityData'
        ));
    }
}
