<?php

namespace App\Http\Controllers;

use App\Models\AgentDefaultProfile;
use App\Models\User;

class WidgetController extends Controller
{
    private const ROLE_LABELS = [
        'buyer'    => 'Buyer Agent',
        'seller'   => 'Seller Agent',
        'tenant'   => 'Tenant Agent',
        'landlord' => 'Landlord Agent',
    ];

    private const PROPERTY_TYPE_LABELS = [
        'residential' => 'Residential',
        'income'      => 'Income Property',
        'commercial'  => 'Commercial',
        'business'    => 'Business Opportunity',
        'vacant_land' => 'Vacant Land',
    ];

    private const VALID_ROLES = ['buyer', 'seller', 'landlord', 'tenant'];

    private const VALID_PROPERTY_TYPES = [
        'residential',
        'income',
        'commercial',
        'business',
        'vacant_land',
    ];

    public function show(string $agentShortId, string $role, string $propertyType = 'residential')
    {
        if (!in_array($role, self::VALID_ROLES, true)) {
            return $this->unavailable();
        }

        if (!in_array($propertyType, self::VALID_PROPERTY_TYPES, true)) {
            $propertyType = 'residential';
        }

        $agent = User::where('short_id', $agentShortId)
            ->where('user_type', 'agent')
            ->first();

        if (!$agent) {
            return $this->unavailable();
        }

        $profile = AgentDefaultProfile::findForAgentWithFallback(
            $agent->id,
            $role,
            $propertyType
        );

        $services = HireAgentDirectController::resolveServices($profile);

        if (!$profile || count($services) === 0) {
            return $this->unavailable();
        }

        $agentName     = trim(($profile->profile_data['first_name'] ?? '') . ' ' . ($profile->profile_data['last_name'] ?? ''));
        if (empty($agentName)) {
            $agentName = $agent->name ?? 'Agent';
        }

        $brokerage         = $profile->profile_data['brokerage'] ?? '';
        $roleLabel         = self::ROLE_LABELS[$role]                       ?? ucfirst($role) . ' Agent';
        $propertyTypeLabel = self::PROPERTY_TYPE_LABELS[$propertyType]      ?? ucwords(str_replace('_', ' ', $propertyType));
        $serviceCount      = count($services);
        $hireUrl           = route('agent.profile.public', [
            'agentShortId' => $agentShortId,
        ]);

        return response()->view('widget.hire', compact(
            'agentName',
            'brokerage',
            'roleLabel',
            'propertyTypeLabel',
            'serviceCount',
            'hireUrl'
        ))->header('X-Frame-Options', 'ALLOWALL');
    }

    private function unavailable()
    {
        return response()->view('widget.unavailable', [], 404)
            ->header('X-Frame-Options', 'ALLOWALL');
    }
}
