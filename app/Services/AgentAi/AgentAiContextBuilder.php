<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;

/**
 * AgentAiContextBuilder
 *
 * Assembles the full context payload for a given scope and listing/entity ID
 * by invoking registered loaders from AgentAiContextSourceRegistry and
 * enforcing the token budget.
 *
 * GOVERNANCE: Read-only. No DB writes. No external HTTP calls.
 * Must never include private fields (user_id, compensation, accepted-bid data).
 */
class AgentAiContextBuilder
{
    public function __construct(
        private readonly AgentAiContextSourceRegistry $registry,
    ) {}

    /**
     * Build the assembled context payload for the given scope.
     *
     * @param  AgentAiContextScope $scope
     * @param  int                 $entityId   Listing or agent profile ID
     * @param  array               $options    Optional hints passed through to loaders
     * @return array{
     *   scope: string,
     *   entity_id: int,
     *   fragments: array,
     *   total_token_estimate: int,
     *   missing_sources: array,
     *   assembled_at: string,
     * }
     */
    public function build(AgentAiContextScope $scope, int $entityId, array $options = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
