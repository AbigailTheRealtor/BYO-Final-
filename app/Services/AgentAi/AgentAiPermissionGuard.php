<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use App\Models\AgentAiChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AgentAiPermissionGuard
 *
 * Enforces access control for Agent AI V2 chat requests.
 *
 * Build 3 implements:
 *   - check()               — validates an HTTP request carrying a session token,
 *                             resolves the session, checks agent ownership.
 *   - validateAgentScope()  — validates agentId against the listing/criteria owner
 *                             before any loader runs.
 *
 * BLOCKED TABLES: The following tables contain bid, offer, and counter-offer
 * data that must NEVER appear in any context query performed by the V2 pipeline:
 *
 *   seller_agent_auction_bids
 *   buyer_agent_auction_bids
 *   landlord_agent_auction_bids
 *   tenant_agent_auction_bids
 *   property_auction_bids
 *   buyer_criteria_auction_bids
 *   seller_offer_listing_bids  (any future offer bid table)
 *   accepted_bid_summaries
 *   counter_bids
 *   seller_counter_bids
 *   landlord_countered_terms
 *   tenant_countered_terms
 *   buyer_countered_terms
 *
 * GOVERNANCE: No DB writes. No external calls. Must never expose private
 * listing or agent data to unauthenticated callers unless the scope is
 * explicitly public.
 */
class AgentAiPermissionGuard
{
    /**
     * Tables that must never be queried during any V2 context assembly.
     * Asserted in integration tests (Build 3 step 11d).
     */
    public const BLOCKED_TABLES = [
        'seller_agent_auction_bids',
        'buyer_agent_auction_bids',
        'landlord_agent_auction_bids',
        'tenant_agent_auction_bids',
        'property_auction_bids',
        'buyer_criteria_auction_bids',
        'seller_offer_listing_bids',
        'accepted_bid_summaries',
        'counter_bids',
        'seller_counter_bids',
        'landlord_countered_terms',
        'tenant_countered_terms',
        'buyer_countered_terms',
    ];

    /**
     * Check whether the given request is allowed to proceed.
     *
     * Validates:
     *   1. session_token is present in the request payload.
     *   2. A matching AgentAiChatSession exists.
     *   3. The session has not been ended.
     *   4. The session's agent_id resolves to a valid agent user.
     *
     * Returns an array describing permission state. Never throws; the caller
     * (AgentAiChatController) converts the `allowed: false` result to a 403/404.
     *
     * @param  Request             $request
     * @param  AgentAiContextScope $scope
     * @param  array               $options
     * @return array{
     *   allowed: bool,
     *   reason: string|null,
     *   http_status: int,
     *   session: AgentAiChatSession|null,
     * }
     */
    public function check(Request $request, AgentAiContextScope $scope, array $options = []): array
    {
        $token = $request->input('session_token');

        if (empty($token)) {
            return [
                'allowed'     => false,
                'reason'      => 'missing_session_token',
                'http_status' => 400,
                'session'     => null,
            ];
        }

        $session = AgentAiChatSession::where('session_token', $token)->first();

        if ($session === null) {
            return [
                'allowed'     => false,
                'reason'      => 'session_not_found',
                'http_status' => 404,
                'session'     => null,
            ];
        }

        if ($session->ended_at !== null) {
            return [
                'allowed'     => false,
                'reason'      => 'session_ended',
                'http_status' => 403,
                'session'     => null,
            ];
        }

        $userType = DB::table('users')
            ->where('id', $session->agent_id)
            ->value('user_type');

        if ($userType === null) {
            return [
                'allowed'     => false,
                'reason'      => 'agent_not_found',
                'http_status' => 403,
                'session'     => null,
            ];
        }

        if ($userType !== 'agent') {
            return [
                'allowed'     => false,
                'reason'      => 'not_an_agent',
                'http_status' => 403,
                'session'     => null,
            ];
        }

        // ── Re-validate session ownership on every ask ────────────────────────
        // Resolves the session's stored scope and confirms the agent still owns
        // the associated listing (or is a valid agent for profile scope).
        // This prevents hijacking via a recycled or stolen session token.
        $sessionScope = AgentAiContextScope::tryFrom((string) $session->scope);
        if ($sessionScope !== null) {
            try {
                $this->validateAgentScope(
                    $sessionScope,
                    (int) $session->agent_id,
                    $session->listing_type,
                    $session->listing_id !== null ? (int) $session->listing_id : null
                );
            } catch (AgentAiPermissionException $e) {
                return [
                    'allowed'     => false,
                    'reason'      => $e->getReason(),
                    'http_status' => $e->getHttpStatus(),
                    'session'     => null,
                ];
            }
        }

        return [
            'allowed'     => true,
            'reason'      => null,
            'http_status' => 200,
            'session'     => $session,
        ];
    }

    /**
     * Validate that the given agentId is the owner of the listing/criteria
     * associated with the requested scope.
     *
     * For listing scopes (seller, landlord, buyer, tenant): the listing's
     * user_id must match $agentId.
     *
     * For agent_profile scope: the agentId must resolve to a valid agent user
     * (user_type = 'agent'). No listing ownership check is needed — the agent
     * is loading their own profile.
     *
     * Uses raw DB queries (not Eloquent) per the postgres-gate-resolver.md memory
     * entry: Eloquent $with eager-loads abort PG transactions on query errors.
     *
     * @param  AgentAiContextScope $scope
     * @param  int                 $agentId
     * @param  string|null         $listingType
     * @param  int|null            $listingId
     * @throws AgentAiPermissionException  When agent does not own the listing.
     */
    public function validateAgentScope(
        AgentAiContextScope $scope,
        int $agentId,
        ?string $listingType,
        ?int $listingId
    ): void {
        if ($agentId <= 0) {
            throw new AgentAiPermissionException(
                'Invalid agent ID.',
                'invalid_agent_id'
            );
        }

        if ($scope === AgentAiContextScope::AgentProfile) {
            $this->assertAgentExists($agentId);
            return;
        }

        if ($listingId === null || $listingId <= 0) {
            throw new AgentAiPermissionException(
                'A valid listing ID is required for listing scopes.',
                'missing_listing_id'
            );
        }

        $table = $this->resolveTable($scope);
        if ($table === null) {
            throw new AgentAiPermissionException(
                'Unrecognised scope for ownership check.',
                'unknown_scope'
            );
        }

        $ownerId = DB::table($table)
            ->where('id', $listingId)
            ->value('user_id');

        if ($ownerId === null) {
            throw new AgentAiPermissionException(
                'Listing not found.',
                'listing_not_found',
                404
            );
        }

        if ((int) $ownerId !== $agentId) {
            throw new AgentAiPermissionException(
                'Agent does not own this listing.',
                'ownership_mismatch'
            );
        }
    }

    /**
     * Assert that a user with the given ID exists and is an agent.
     *
     * @param  int $agentId
     * @throws AgentAiPermissionException
     */
    private function assertAgentExists(int $agentId): void
    {
        $userType = DB::table('users')
            ->where('id', $agentId)
            ->value('user_type');

        if ($userType === null) {
            throw new AgentAiPermissionException(
                'Agent not found.',
                'agent_not_found',
                404
            );
        }

        if ($userType !== 'agent') {
            throw new AgentAiPermissionException(
                'User is not an agent.',
                'not_an_agent',
                403
            );
        }
    }

    /**
     * Map a listing scope to its underlying DB table name.
     *
     * @param  AgentAiContextScope $scope
     * @return string|null
     */
    private function resolveTable(AgentAiContextScope $scope): ?string
    {
        return match ($scope) {
            AgentAiContextScope::PublicListingSeller   => 'seller_agent_auctions',
            AgentAiContextScope::PublicListingLandlord => 'landlord_agent_auctions',
            AgentAiContextScope::BuyerCriteria         => 'buyer_agent_auctions',
            AgentAiContextScope::TenantCriteria        => 'tenant_agent_auctions',
            default                                    => null,
        };
    }
}
