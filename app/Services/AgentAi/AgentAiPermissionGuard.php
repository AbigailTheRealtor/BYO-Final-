<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;
use Illuminate\Http\Request;

/**
 * AgentAiPermissionGuard
 *
 * Enforces access control for Agent AI V2 chat requests. Checks scope
 * visibility rules, authentication requirements, and rate limit eligibility
 * before the pipeline is allowed to proceed.
 *
 * GOVERNANCE: No DB writes. No external calls. Must never expose private
 * listing or agent data to unauthenticated callers unless the scope is
 * explicitly public.
 */
class AgentAiPermissionGuard
{
    /**
     * Check whether the given request is allowed to proceed for the scope.
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
        throw new \RuntimeException('Not implemented');
    }
}
