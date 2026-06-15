<?php

namespace App\Services\AgentAi;

use App\Models\AgentAiChatLead;
use Illuminate\Support\Facades\Log;

/**
 * AgentAiEscalationService
 *
 * Handles human escalation paths: when the visitor confirms they want to speak
 * with the agent directly, this service creates an agent_question lead record
 * flagged for priority follow-up.
 *
 * GOVERNANCE:
 *  - No external HTTP calls.
 *  - DB writes to agent_ai_chat_leads only.
 *  - Must never expose private listing data in escalation payloads.
 *  - Escalation records are scoped to the owning agent; never shared.
 */
class AgentAiEscalationService
{
    private AgentAiLeadCaptureService $captureService;

    public function __construct(AgentAiLeadCaptureService $captureService)
    {
        $this->captureService = $captureService;
    }

    /**
     * Handle a confirmed escalation request from a visitor.
     *
     * Creates or updates the lead record with lead_type = agent_question,
     * stores the exact question as intent_phrase, and flags for priority follow-up.
     *
     * @param  int    $sessionId     The chat session ID.
     * @param  int    $agentId       The agent who owns this session.
     * @param  string $question      The visitor's exact unanswered question.
     * @param  array  $visitorData   Optional visitor contact info {name, email, phone}.
     * @return array{
     *   escalated: bool,
     *   lead_id: int|null,
     *   suggested_cta: string|null,
     *   error: string|null,
     * }
     */
    public function confirmEscalation(
        int    $sessionId,
        int    $agentId,
        string $question,
        array  $visitorData = []
    ): array {
        try {
            $lead = $this->captureService->recordOrUpdate($sessionId, array_filter([
                'lead_type'            => AgentAiChatLead::LEAD_TYPE_AGENT_QUESTION,
                'intent_phrase'        => $question,
                'requested_action'     => 'human_escalation',
                'recommended_follow_up' => 'Visitor requested direct contact. Priority follow-up required.',
                'visitor_name'         => $visitorData['name'] ?? null,
                'visitor_email'        => $visitorData['email'] ?? null,
                'visitor_phone'        => $visitorData['phone'] ?? null,
                'question'             => $question,
            ], fn ($v) => $v !== null));

            Log::info('AgentAiEscalationService: escalation confirmed', [
                'session_id' => $sessionId,
                'agent_id'   => $agentId,
                'lead_id'    => $lead->id,
            ]);

            return [
                'escalated'     => true,
                'lead_id'       => $lead->id,
                'suggested_cta' => 'Your question has been sent to the agent. They will follow up with you shortly.',
                'error'         => null,
            ];
        } catch (\Throwable $e) {
            Log::error('AgentAiEscalationService: escalation failed', [
                'session_id' => $sessionId,
                'agent_id'   => $agentId,
                'error'      => $e->getMessage(),
            ]);

            return [
                'escalated'     => false,
                'lead_id'       => null,
                'suggested_cta' => null,
                'error'         => 'Escalation could not be recorded at this time.',
            ];
        }
    }

    /**
     * Build the escalation prompt text to show the visitor when escalate: true.
     *
     * This is a deterministic string — no AI calls.
     */
    public function buildEscalationPrompt(): string
    {
        return "I'm not able to provide a confident answer to that question. "
            . "Would you like me to send your question directly to the agent so they can follow up with you? "
            . "If so, please share your name and the best way to reach you.";
    }
}
