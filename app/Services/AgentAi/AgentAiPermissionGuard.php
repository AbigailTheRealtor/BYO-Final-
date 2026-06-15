<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;
use App\Exceptions\AgentAiPermissionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AgentAiPermissionGuard
 *
 * Enforces access control for Agent AI V2 chat requests. This partial Build 2
 * implementation adds agent-ownership validation for listing and criteria scopes.
 *
 * Build 2 implements: validateAgentScope() — validates agentId against the
 * listing/criteria owner before any loader runs, throwing
 * AgentAiPermissionException on mismatch.
 *
 * Build 3 will complete: HTTP request check(), rate limit eligibility, and
 * full authentication requirements.
 *
 * GOVERNANCE: No DB writes. No external calls. Must never expose private
 * listing or agent data to unauthenticated callers unless the scope is
 * explicitly public.
 */
class AgentAiPermissionGuard
{
    /**
     * Check whether the given request is allowed to proceed for the scope.
     * (Build 3 — not yet fully implemented)
     *
     * @param  Request             $request
     * @param  AgentAiContextScope $scope
     * @param  array               $options
     * @return array{
     *   allowed: bool,
     *   reason: string|null,
     *   http_status: int,
     * }
     */
    public function check(Request $request, AgentAiContextScope $scope, array $options = []): array
    {
        throw new \RuntimeException('AgentAiPermissionGuard::check() is not yet implemented — Build 3.');
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
     * Uses raw DB to avoid Eloquent eager-load transaction poisoning.
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
