<?php

namespace App\Services\AgentAi;

/**
 * AgentAiLeadCaptureService
 *
 * Captures and persists lead signals generated during an Agent AI V2 chat
 * session (e.g. contact requests, showing requests, hire-me intent detected
 * by AgentAiActionResolver).
 *
 * GOVERNANCE: May write to the leads/hire_agent_leads tables only.
 * Must never write to listing, bid, or user tables.
 */
class AgentAiLeadCaptureService
{
    /**
     * Capture a lead signal from a chat session.
     *
     * @param  string     $actionType   e.g. 'contact_request', 'showing_request', 'hire_intent'
     * @param  int        $agentId      ID of the agent whose chat triggered the lead
     * @param  array      $sessionData  Relevant context from the chat session
     * @param  array      $visitorData  Visitor metadata (IP, session_id, user_id if authenticated)
     * @return array{success: bool, lead_id: int|null, error: string|null}
     */
    public function capture(string $actionType, int $agentId, array $sessionData, array $visitorData = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
