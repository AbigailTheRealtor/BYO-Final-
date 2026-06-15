<?php

namespace App\Services\AgentAi;

/**
 * AgentAiEscalationService
 *
 * Handles escalation paths when the AI cannot answer a question (e.g.
 * "insufficient_context", repeated "unsupported" questions). Escalation
 * actions include surfacing a "Contact Agent Directly" CTA, logging the
 * unanswerable question for the agent to review, and optionally queueing
 * a notification to the agent.
 *
 * GOVERNANCE: No external HTTP calls. No DB writes beyond the escalation
 * log table. Must never expose private listing data in escalation payloads.
 */
class AgentAiEscalationService
{
    /**
     * Handle an escalation event for a chat session.
     *
     * @param  string $reason        Why escalation was triggered (e.g. 'insufficient_context', 'repeated_unsupported')
     * @param  int    $agentId       Agent whose listing/profile triggered the escalation
     * @param  array  $sessionData   Session context including the unanswered question(s)
     * @return array{
     *   escalated: bool,
     *   escalation_id: int|null,
     *   suggested_cta: string|null,
     *   error: string|null,
     * }
     */
    public function escalate(string $reason, int $agentId, array $sessionData = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
