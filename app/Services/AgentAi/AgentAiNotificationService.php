<?php

namespace App\Services\AgentAi;

/**
 * AgentAiNotificationService
 *
 * Dispatches notifications to agents when chat-generated leads or high-value
 * signals are captured (e.g. "A visitor asked 5 questions and requested a
 * showing via Agent AI").
 *
 * GOVERNANCE: Notifications only. No external API calls beyond Laravel's
 * built-in notification channels. No bid, compensation, or offer data
 * may appear in notification payloads.
 */
class AgentAiNotificationService
{
    /**
     * Notify the agent about a captured lead or engagement signal.
     *
     * @param  int    $agentId      Agent user ID to notify
     * @param  string $eventType    e.g. 'lead_captured', 'showing_request', 'hire_intent'
     * @param  array  $payload      Event payload (lead ID, session summary, CTA type, etc.)
     * @return array{success: bool, channel: string|null, error: string|null}
     */
    public function notify(int $agentId, string $eventType, array $payload = []): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
