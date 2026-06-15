<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;

/**
 * AgentAiContextScopeResolver
 *
 * Resolves the correct AgentAiContextScope for a given request context
 * (listing type, page URL, user role, etc.).
 *
 * GOVERNANCE: Read-only. No DB writes. No external HTTP calls.
 */
class AgentAiContextScopeResolver
{
    /**
     * Resolve the context scope from a listing type string and optional role hint.
     *
     * @param  string      $listingType  e.g. 'seller', 'landlord', 'buyer', 'tenant', 'agent_profile'
     * @param  array       $options      Optional hints (e.g. ['role' => 'agent'])
     * @return AgentAiContextScope
     */
    public function resolve(string $listingType, array $options = []): AgentAiContextScope
    {
        throw new \RuntimeException('Not implemented');
    }
}
