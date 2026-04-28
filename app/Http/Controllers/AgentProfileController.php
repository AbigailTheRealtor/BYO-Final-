<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use App\Services\AgentPresetCatalog;
use Illuminate\Support\Facades\Auth;

class AgentProfileController extends Controller
{
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
