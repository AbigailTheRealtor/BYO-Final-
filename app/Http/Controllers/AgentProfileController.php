<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentPresetCatalog;
use Illuminate\Support\Facades\Auth;

class AgentProfileController extends Controller
{
    /**
     * Keys from profile_data that are safe to expose on the public profile page.
     * Only these keys are passed to the Blade view — all other keys (compensation
     * structures, referral fees, agency agreement terms, broker fee details, etc.)
     * are stripped server-side before the view is rendered.
     */
    public const PUBLIC_PROFILE_KEYS = [
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
    ];

    public function show(string $agentShortId)
    {
        $agent = User::where('short_id', $agentShortId)
            ->where('user_type', 'agent')
            ->firstOrFail();

        $isOwnerPreview = Auth::check() && Auth::id() === $agent->id;

        $profiles = AgentDefaultProfile::where('user_id', $agent->id)->get();

        // Build one Hire button per role. For each role, iterate property types in
        // catalog order and use the first one that has a valid preset with services.
        $hireButtons = [];
        foreach (AgentPresetCatalog::getRoles() as $role) {
            $primaryPropertyType = null;
            foreach (AgentPresetCatalog::getPropertyTypes($role) as $propertyType) {
                $profile = $profiles->first(function ($p) use ($role, $propertyType) {
                    return $p->role_type === $role && $p->property_type === $propertyType;
                });
                if (!$profile) {
                    continue;
                }
                $services = HireAgentDirectController::resolveServices($profile);
                if (count($services) > 0) {
                    $primaryPropertyType = $propertyType;
                    break;
                }
            }
            if ($primaryPropertyType !== null) {
                $hireButtons[] = [
                    'role'         => $role,
                    'propertyType' => $primaryPropertyType,
                    'roleLabel'    => AgentDefaultProfile::roleLabel($role),
                    'propLabel'    => AgentDefaultProfile::propertyLabel($primaryPropertyType),
                    'url'          => route('hire.agent.public', [
                        'agentShortId' => $agentShortId,
                        'role'         => $role,
                        'propertyType' => $primaryPropertyType,
                    ]),
                ];
            }
        }

        // Prefer the most recently updated profile that has actual bio content;
        // fall back to the most recently updated profile overall.
        $primaryProfile = $profiles->sortByDesc('updated_at')
                ->first(fn($p) => !empty($p->profile_data['bio']))
            ?? $profiles->sortByDesc('updated_at')->first()
            ?? null;
        $data = $primaryProfile?->profile_data ?? [];

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

        return view('agent-profile.show', compact(
            'agent',
            'data',
            'hireButtons',
            'isOwnerPreview',
            'agentShortId'
        ));
    }
}
