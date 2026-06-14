{{--
    Agent-Side Compatibility Read-Only Display Partial
    ====================================================
    Included on bid detail pages to show agent's working style & compatibility answers,
    followed by the Location Match insights section (Phase 6D integration point).

    Requires: $bid (an agent bid model instance with HasCompatibilityPreferences trait)

    Integration note — Location Match (Phase 6D):
      This is the single canonical integration point for LocationMatchIntegrationService
      output across all four roles (seller, buyer, landlord, tenant). It is chosen because
      this partial is the deepest shared include that reaches all four role bid-detail render
      paths without duplication. Adding the section inside each role-specific
      representation-compatibility tab (the listing-creation form chain) would cause a
      second appearance on pages that render both chains. The location-match-insights
      partial is @included exactly once here, eliminating that risk.
--}}
@php
    $compat = method_exists($bid, 'loadCompatibilityPreferences')
        ? $bid->loadCompatibilityPreferences()
        : [];

    $hasAnyCompatData = false;
    foreach ($compat as $sectionData) {
        if (is_array($sectionData) && !empty($sectionData)) {
            foreach ($sectionData as $v) {
                if (!empty($v)) { $hasAnyCompatData = true; break 2; }
            }
        }
    }

    $compatLabels = [
        'agent_communication_channels'      => 'Preferred Communication Channels',
        'agent_communication_frequency'     => 'Proactive Update Cadence',
        'agent_response_time_commitment'    => 'Response Time Commitment',
        'agent_communication_notes'         => 'Communication Notes',
        'agent_availability_notes'          => 'General Availability',
        'agent_negotiation_style'           => 'Negotiation Style',
        'agent_negotiation_notes'           => 'Negotiation Notes',
        'agent_guidance_level'              => 'Level of Guidance',
        'agent_guidance_notes'              => 'Guidance Notes',
        'agent_collaboration_style'         => 'Collaboration Style',
        'agent_availability_windows'        => 'Availability Windows',
        'agent_transaction_pace'            => 'Transaction Pace',
        'agent_strategy_experience'         => 'Transaction Experience',
        'agent_strategy_notes'              => 'Strategy Notes',
        'agent_decision_support_style'      => 'Decision Support Style',
        'agent_risk_posture'                => 'Risk Posture',
        'agent_representation_philosophy'   => 'Representation Philosophy',
        'agent_philosophy_narrative'        => 'Philosophy Narrative',
        'agent_philosophy_notes'            => 'Philosophy Notes',
        'agent_representation_priorities'   => 'Capability Strengths',
        'agent_priority_notes'              => 'Priority Notes',
    ];

    $sectionMeta = [
        'communication_preferences'  => ['icon' => 'fa-comments',       'label' => 'Communication Preferences'],
        'negotiation_approach'       => ['icon' => 'fa-scale-balanced',  'label' => 'Negotiation Approach'],
        'guidance_style'             => ['icon' => 'fa-compass',         'label' => 'Guidance Style'],
        'collaboration_preferences'  => ['icon' => 'fa-people-arrows',   'label' => 'Collaboration Preferences'],
        'transaction_strategy'       => ['icon' => 'fa-chess-knight',    'label' => 'Transaction Strategy'],
        'representation_philosophy'  => ['icon' => 'fa-shield-halved',   'label' => 'Representation Philosophy'],
        'representation_priorities'  => ['icon' => 'fa-list-check',      'label' => 'Representation Priorities'],
    ];
@endphp

@if ($hasAnyCompatData)
<div class="mt-4">
    <div class="p-3 rounded mb-3" style="background: #f0f9f9; border: 1px solid #b2dfdb;">
        <h6 class="mb-0 fw-bold" style="color: #036b70;">
            <i class="fa-solid fa-handshake-simple me-2"></i>Working Style &amp; Compatibility
        </h6>
        <div class="small text-muted mt-1">This agent has shared how they work with clients.</div>
    </div>

    @foreach ($sectionMeta as $sectionKey => $meta)
        @php $sectionData = $compat[$sectionKey] ?? null; @endphp
        @if (is_array($sectionData) && !empty(array_filter($sectionData, fn($v) => !empty($v))))
        <div class="mb-3 p-3 rounded" style="background: #fafafa; border: 1px solid #e9ecef;">
            <div class="fw-semibold mb-2 small" style="color: #049399;">
                <i class="fa-solid {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
            </div>
            <div class="row g-2">
                @foreach ($sectionData as $fieldKey => $fieldValue)
                    @if (!empty($fieldValue))
                    <div class="col-md-6 mb-1">
                        <div class="small fw-semibold text-muted">{{ $compatLabels[$fieldKey] ?? ucwords(str_replace('_', ' ', $fieldKey)) }}</div>
                        <div class="small">
                            @if (is_array($fieldValue))
                                {{ implode(', ', array_filter($fieldValue)) }}
                            @else
                                {{ $fieldValue }}
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
</div>
@endif

{{--
    ─── Phase 6D: Location Match Integration ──────────────────────────────────────
    Single canonical include point for the Location Match section across all four
    roles (seller, buyer, landlord, tenant).

    $locationMatchInsights is populated upstream by the controller:
      • BuyerAgentAuctionController::bidDetail()          — real insights or []
      • TenantAgentAuctionController::viewBidPreview()    — real insights or []
      • SellerAgentAuctionController / LandlordAgentAuction — not set → defaults to []

    When $locationMatchInsights is [] (seller/landlord, or buyer/tenant with no
    matching data), the partial renders "No location match data available."
    When it carries insight strings, it renders the bulleted insight list.

    Extraction and service orchestration live in:
      App\Services\LocationDna\LocationMatchAuctionExtractor
--}}
@include('partials.location-match-insights', ['insights' => $locationMatchInsights ?? []])
