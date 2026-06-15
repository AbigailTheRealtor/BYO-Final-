<?php

namespace App\Services\AgentAi;

use App\Enums\AgentAiContextScope;

/**
 * AgentAiActionResolver
 *
 * Inspects a completed chat answer and session context to resolve which
 * call-to-action (if any) should be surfaced to the visitor. CTAs include
 * "Contact Agent", "Schedule Showing", "Hire This Agent", "Get Pre-Approved".
 *
 * GOVERNANCE: Read-only resolver. No DB writes. No external calls.
 * CTA selection must never be based on protected class signals.
 */
class AgentAiActionResolver
{
    /**
     * Resolve the appropriate CTA for a completed chat turn.
     *
     * @param  array               $finalResponse  Output of AgentAiFinalResponseBuilder::build()
     * @param  AgentAiContextScope $scope
     * @param  array               $sessionData    Accumulated signals from the current session
     * @return array{
     *   cta_type: string|null,
     *   cta_label: string|null,
     *   cta_url: string|null,
     *   cta_payload: array,
     * }
     */
    public function resolve(array $finalResponse, AgentAiContextScope $scope, array $sessionData = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
