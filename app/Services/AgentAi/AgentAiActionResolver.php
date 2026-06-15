<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Models\AgentDefaultProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * AgentAiActionResolver
 *
 * Resolves the ordered set of call-to-action buttons that accompany every AI
 * response, and handles the special "View Agent's Services" inline response
 * that bypasses the OpenAI pipeline entirely.
 *
 * GOVERNANCE:
 *   - Read-only. No DB writes. No external HTTP calls.
 *   - CTA selection must never be based on protected-class signals.
 *   - Inline services response is built from an explicit public-safe whitelist
 *     (INLINE_SERVICES_ALLOWED_KEYS). Every other key from profile_data is
 *     excluded — including all compensation amounts, percentages, flat fees,
 *     retainers, referral fees, and any private contact information.
 *     See AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md Section 7.1.
 *   - Action objects follow the canonical schema:
 *       { label, action_key, href|null, unavailable_reason|null }
 *     This is the shared contract between Build 4, Build 5 (lead capture),
 *     and Build 6 (button rendering).
 *   - href is non-null only when the platform URL was successfully resolved.
 *   - unavailable_reason is a machine-readable string set whenever href is null
 *     due to missing context or route failure:
 *       'missing_listing_id' — listing scope action, no listing_id in session
 *       'agent_not_found'    — consultation action, agent short_id not found
 *       'route_unavailable'  — context present but platform route resolved to null
 */
class AgentAiActionResolver
{
    // ── Action key constants ──────────────────────────────────────────────────

    public const ACTION_SCHEDULE_SHOWING      = 'schedule_showing';
    public const ACTION_SUBMIT_OFFER          = 'submit_offer';
    public const ACTION_SCHEDULE_TOUR         = 'schedule_tour';
    public const ACTION_SUBMIT_RENTAL_OFFER   = 'submit_rental_offer';
    public const ACTION_CONTACT_AGENT         = 'contact_agent';
    public const ACTION_REQUEST_MORE_INFO     = 'request_more_information';
    public const ACTION_VIEW_AGENT_SERVICES   = 'view_agent_services';
    public const ACTION_SCHEDULE_CONSULTATION = 'schedule_consultation';
    public const ACTION_VIEW_LISTINGS         = 'view_listings';
    public const ACTION_RESPOND_TO_BUYER      = 'respond_to_buyer_criteria';
    public const ACTION_RESPOND_TO_TENANT     = 'respond_to_tenant_criteria';
    public const ACTION_ASK_A_QUESTION        = 'ask_a_question';

    // ── Scope → ordered action keys ───────────────────────────────────────────

    /**
     * Canonical action set per scope, in display order.
     *
     * Invariants:
     *   - Listing scopes always contain contact_agent and request_more_information.
     *   - All scopes contain view_agent_services.
     */
    private const SCOPE_ACTION_KEYS = [
        'public_listing_seller' => [
            self::ACTION_SCHEDULE_SHOWING,
            self::ACTION_SUBMIT_OFFER,
            self::ACTION_CONTACT_AGENT,
            self::ACTION_REQUEST_MORE_INFO,
            self::ACTION_VIEW_AGENT_SERVICES,
            self::ACTION_SCHEDULE_CONSULTATION,
        ],
        'public_listing_landlord' => [
            self::ACTION_SCHEDULE_TOUR,
            self::ACTION_SUBMIT_RENTAL_OFFER,
            self::ACTION_CONTACT_AGENT,
            self::ACTION_REQUEST_MORE_INFO,
            self::ACTION_VIEW_AGENT_SERVICES,
            self::ACTION_SCHEDULE_CONSULTATION,
        ],
        'buyer_criteria' => [
            self::ACTION_RESPOND_TO_BUYER,
            self::ACTION_CONTACT_AGENT,
            self::ACTION_REQUEST_MORE_INFO,
            self::ACTION_VIEW_AGENT_SERVICES,
        ],
        'tenant_criteria' => [
            self::ACTION_RESPOND_TO_TENANT,
            self::ACTION_CONTACT_AGENT,
            self::ACTION_REQUEST_MORE_INFO,
            self::ACTION_VIEW_AGENT_SERVICES,
        ],
        'agent_profile' => [
            self::ACTION_VIEW_AGENT_SERVICES,
            self::ACTION_SCHEDULE_CONSULTATION,
            self::ACTION_CONTACT_AGENT,
            self::ACTION_VIEW_LISTINGS,
        ],
    ];

    // ── Human-readable labels ─────────────────────────────────────────────────

    private const ACTION_LABELS = [
        self::ACTION_SCHEDULE_SHOWING      => 'Schedule a Showing',
        self::ACTION_SUBMIT_OFFER          => 'Submit an Offer',
        self::ACTION_SCHEDULE_TOUR         => 'Schedule a Tour',
        self::ACTION_SUBMIT_RENTAL_OFFER   => 'Submit a Rental Application',
        self::ACTION_CONTACT_AGENT         => 'Contact Agent',
        self::ACTION_REQUEST_MORE_INFO     => 'Request More Information',
        self::ACTION_VIEW_AGENT_SERVICES   => "View Agent's Services",
        self::ACTION_SCHEDULE_CONSULTATION => 'Schedule a Consultation',
        self::ACTION_VIEW_LISTINGS         => 'View Listings',
        self::ACTION_RESPOND_TO_BUYER      => 'Respond to Buyer Criteria',
        self::ACTION_RESPOND_TO_TENANT     => 'Respond to Tenant Criteria',
        self::ACTION_ASK_A_QUESTION        => 'Ask a Question',
    ];

    /**
     * Action keys that are handled in-chat and never get a platform URL.
     * The frontend renders these without navigation; href is always null.
     */
    private const IN_CHAT_ACTIONS = [
        self::ACTION_VIEW_AGENT_SERVICES,
        self::ACTION_CONTACT_AGENT,
        self::ACTION_REQUEST_MORE_INFO,
        self::ACTION_ASK_A_QUESTION,
    ];

    /**
     * Explicit whitelist of profile_data keys permitted in the inline services
     * response. Every key NOT in this list is silently excluded — including all
     * compensation amounts, fee percentages, flat fees, retainer amounts,
     * referral fees, and private contact fields.
     *
     * Per audit Section 7.1 and AGENT_AI_ASSISTANT_CONTEXT_SOURCE_MAP.md.
     * Only service-type labels and structural type fields are exposed.
     */
    private const INLINE_SERVICES_ALLOWED_KEYS = [
        'services',
        'other_services',
        'commission_structure',
        'commission_structure_type',
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Resolve the full ordered action set for a completed chat turn.
     *
     * Every action object follows the canonical schema:
     *   { label, action_key, href|null, unavailable_reason|null }
     *
     * href is null for in-chat actions (view_agent_services, contact_agent,
     * request_more_information) or when required context is missing/route
     * generation fails, in which case unavailable_reason is set.
     *
     * @param  AgentAiContextScope $scope
     * @param  int                 $agentId
     * @param  int|null            $listingId
     * @return array<int, array{label: string, action_key: string, href: string|null, unavailable_reason: string|null}>
     */
    public function resolve(AgentAiContextScope $scope, int $agentId, ?int $listingId): array
    {
        $actionKeys   = self::SCOPE_ACTION_KEYS[$scope->value] ?? [];
        $agentShortId = $this->fetchAgentShortId($agentId);
        $actions      = [];

        foreach ($actionKeys as $key) {
            [$href, $unavailableReason] = $this->resolveHref($key, $scope, $listingId, $agentShortId);

            $actions[] = [
                'label'              => self::ACTION_LABELS[$key] ?? $key,
                'action_key'         => $key,
                'href'               => $href,
                'unavailable_reason' => $unavailableReason,
            ];
        }

        return $actions;
    }

    /**
     * Build the inline "View Agent's Services" chat response.
     *
     * Called when the frontend submits action_key = 'view_agent_services'.
     * Bypasses the OpenAI pipeline entirely. Only keys in INLINE_SERVICES_ALLOWED_KEYS
     * are read from profile_data — all compensation amounts, fee percentages,
     * retainers, referral fees, and private contact fields are excluded.
     *
     * Returns secondary actions: Schedule Consultation (with resolved href),
     * Contact Agent (in-chat), Ask a Question (in-chat).
     *
     * @param  int $agentId
     * @return array{status: string, answer: string, escalate: bool, actions: array}
     */
    public function resolveInlineServices(int $agentId): array
    {
        $presets = AgentDefaultProfile::where('user_id', $agentId)
            ->orderBy('role_type')
            ->orderBy('property_type')
            ->get();

        if ($presets->isEmpty()) {
            $answer = "I don't have a detailed services list on file at the moment, but I'd be happy to walk you through what I offer. Feel free to ask a specific question or contact me directly.";
        } else {
            $lines = ["Here's a summary of my services:\n"];

            foreach ($presets as $preset) {
                // Pull only the whitelisted keys from profile_data.
                // Every other key is intentionally excluded regardless of its value.
                $data = array_intersect_key(
                    (array) ($preset->profile_data ?? []),
                    array_flip(self::INLINE_SERVICES_ALLOWED_KEYS)
                );

                $roleLabel = AgentDefaultProfile::roleLabel($preset->role_type);
                $propLabel = AgentDefaultProfile::propertyLabel($preset->property_type);
                $header    = "**{$roleLabel} — {$propLabel}**";
                $hasLines  = false;

                $services = $data['services'] ?? [];
                if (is_array($services)) {
                    $services = array_values(array_filter($services));
                }
                if (!empty($services)) {
                    $lines[]  = $header;
                    $lines[]  = 'Services: ' . implode(', ', $services);
                    $hasLines = true;
                }

                $otherServices = $data['other_services'] ?? [];
                if (is_array($otherServices)) {
                    $otherServices = array_values(array_filter($otherServices));
                }
                if (!empty($otherServices)) {
                    if (!$hasLines) {
                        $lines[] = $header;
                    }
                    $lines[]  = 'Additional services: ' . implode(', ', $otherServices);
                    $hasLines = true;
                }

                // commission_structure and commission_structure_type are type/label fields
                // (e.g. "Flat Fee", "Percentage Based") — never dollar amounts or rates.
                $commStructure = $data['commission_structure'] ?? null;
                if (is_string($commStructure) && trim($commStructure) !== '') {
                    if (!$hasLines) {
                        $lines[] = $header;
                    }
                    $lines[]  = 'Fee structure: ' . trim($commStructure);
                    $hasLines = true;
                }

                if ($hasLines) {
                    $lines[] = '';
                }
            }

            $answer = trim(implode("\n", $lines));
        }

        // Resolve schedule_consultation href the same way normal scope resolution does,
        // so the "Schedule a Consultation" CTA navigates to the agent's public profile
        // rather than rendering as a dead or missing button.
        $agentShortId = $this->fetchAgentShortId($agentId);
        [$consultHref, $consultUnavailableReason] = $this->resolveHref(
            self::ACTION_SCHEDULE_CONSULTATION,
            AgentAiContextScope::AgentProfile,
            null,
            $agentShortId
        );

        $secondaryActions = [
            [
                'label'              => self::ACTION_LABELS[self::ACTION_SCHEDULE_CONSULTATION],
                'action_key'         => self::ACTION_SCHEDULE_CONSULTATION,
                'href'               => $consultHref,
                'unavailable_reason' => $consultUnavailableReason,
            ],
            $this->makeAction(self::ACTION_CONTACT_AGENT),
            $this->makeAction(self::ACTION_ASK_A_QUESTION),
        ];

        return [
            'status'   => 'answered',
            'answer'   => $answer,
            'escalate' => false,
            'actions'  => $secondaryActions,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the href and unavailable_reason for a single action key.
     *
     * unavailable_reason semantics:
     *   null                — href is present, or action is intentionally in-chat
     *   'missing_listing_id' — listing scope action with no listing_id in session
     *   'agent_not_found'   — consultation, agent short_id not found in DB
     *   'route_unavailable' — context present, but platform route resolved to null
     *                         (route doesn't exist, is misconfigured, or the URL
     *                          cannot be generated with the given parameters)
     *
     * @return array{0: string|null, 1: string|null}  [href, unavailable_reason]
     */
    private function resolveHref(
        string $actionKey,
        AgentAiContextScope $scope,
        ?int $listingId,
        ?string $agentShortId
    ): array {
        // In-chat actions never get a platform URL; frontend handles them locally.
        if (in_array($actionKey, self::IN_CHAT_ACTIONS, true)) {
            return [null, null];
        }

        switch ($actionKey) {
            // Seller listing workflow actions → seller listing view page
            case self::ACTION_SCHEDULE_SHOWING:
            case self::ACTION_SUBMIT_OFFER:
                if ($listingId === null) {
                    return [null, 'missing_listing_id'];
                }
                return $this->routeOrUnavailable('offer.listing.seller.view', ['id' => $listingId]);

            // Landlord listing workflow actions → landlord listing view page
            case self::ACTION_SCHEDULE_TOUR:
            case self::ACTION_SUBMIT_RENTAL_OFFER:
                if ($listingId === null) {
                    return [null, 'missing_listing_id'];
                }
                return $this->routeOrUnavailable('offer.listing.landlord.view', ['id' => $listingId]);

            // Buyer criteria response → buyer criteria view page
            case self::ACTION_RESPOND_TO_BUYER:
                if ($listingId === null) {
                    return [null, 'missing_listing_id'];
                }
                return $this->routeOrUnavailable('buyer.criteria.view', ['id' => $listingId]);

            // Tenant criteria response → tenant criteria view page
            case self::ACTION_RESPOND_TO_TENANT:
                if ($listingId === null) {
                    return [null, 'missing_listing_id'];
                }
                return $this->routeOrUnavailable('tenant.criteria.auction.view', ['id' => $listingId]);

            // Consultation → agent's public profile page
            case self::ACTION_SCHEDULE_CONSULTATION:
                if ($agentShortId === null) {
                    return [null, 'agent_not_found'];
                }
                return $this->routeOrUnavailable('agent.profile.public', ['agentShortId' => $agentShortId]);

            // View Listings → scope-appropriate search/browse page
            case self::ACTION_VIEW_LISTINGS:
                $routeMap = [
                    AgentAiContextScope::PublicListingSeller->value   => 'offer.listing.seller.searchListing',
                    AgentAiContextScope::PublicListingLandlord->value => 'offer.listing.landlord.searchListing',
                    AgentAiContextScope::BuyerCriteria->value         => 'offer.listing.buyer.searchListing',
                    AgentAiContextScope::TenantCriteria->value        => 'offer.listing.tenant.searchListing',
                    AgentAiContextScope::AgentProfile->value          => 'offer.listing.seller.searchListing',
                ];
                $routeName = $routeMap[$scope->value] ?? null;
                if ($routeName === null) {
                    return [null, 'route_unavailable'];
                }
                return $this->routeOrUnavailable($routeName);
        }

        return [null, null];
    }

    /**
     * Attempt to generate a named platform URL.
     *
     * Returns [href, null] when the route exists and URL generation succeeds.
     * Returns [null, 'route_unavailable'] when the route is missing, the name
     * is not registered, or URL generation throws for any reason.
     *
     * Use this (not safeRoute) for any action that already has the required
     * context — it guarantees unavailable_reason is set when href is null.
     *
     * @return array{0: string|null, 1: string|null}  [href, unavailable_reason]
     */
    private function routeOrUnavailable(string $name, array $params = []): array
    {
        try {
            if (!Route::has($name)) {
                return [null, 'route_unavailable'];
            }
            return [route($name, $params), null];
        } catch (\Throwable) {
            return [null, 'route_unavailable'];
        }
    }

    /**
     * Look up an agent's short_id for use in public platform URLs.
     * Uses a raw DB read per the PostgreSQL Gate resolver pattern (no Eloquent eager-loads).
     *
     * @return string|null  null when not found or agentId is invalid.
     */
    private function fetchAgentShortId(int $agentId): ?string
    {
        if ($agentId <= 0) {
            return null;
        }

        $shortId = DB::table('users')->where('id', $agentId)->value('short_id');
        return $shortId !== null ? (string) $shortId : null;
    }

    /**
     * Build a bare action object for an in-chat action (href always null).
     *
     * Only for use with actions listed in IN_CHAT_ACTIONS. Never use this
     * for workflow-routing actions that should resolve a platform URL.
     *
     * @return array{label: string, action_key: string, href: null, unavailable_reason: null}
     */
    private function makeAction(string $actionKey): array
    {
        return [
            'label'              => self::ACTION_LABELS[$actionKey] ?? $actionKey,
            'action_key'         => $actionKey,
            'href'               => null,
            'unavailable_reason' => null,
        ];
    }
}
