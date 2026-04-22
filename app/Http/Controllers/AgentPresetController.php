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

        $roleLabel     = AgentDefaultProfile::roleLabel($role);
        $propertyLabel = AgentDefaultProfile::propertyLabel($propertyType);

        return view('agent-presets.edit', compact(
            'role', 'propertyType', 'data', 'services',
            'selectedServices', 'roleLabel', 'propertyLabel'
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
        ]);

        $profileData = [
            'services'            => $request->input('services', []),
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
        ];

        AgentDefaultProfile::upsertForAgent(Auth::id(), $role, $propertyType, $profileData);

        return redirect()
            ->route('agent.presets.index')
            ->with('success', AgentDefaultProfile::roleLabel($role) . ' — ' . AgentDefaultProfile::propertyLabel($propertyType) . ' preset saved.');
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
