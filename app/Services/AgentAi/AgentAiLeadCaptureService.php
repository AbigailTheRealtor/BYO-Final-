<?php

namespace App\Services\AgentAi;

use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AgentAiLeadCaptureService
 *
 * Captures and persists lead signals generated during an Agent AI V2 chat
 * session.
 *
 * GOVERNANCE:
 *  - May write to agent_ai_chat_leads only.
 *  - Must never write to listing, bid, or user tables.
 *  - Visitor contact info must never be shared across agents.
 *  - Signed-in visitors are auto-identified from the session (no re-ask).
 *  - Anonymous visitors are prompted for contact info ONLY at high-intent signals.
 */
class AgentAiLeadCaptureService
{
    private AgentAiLeadIntentDetector $detector;
    private AgentAiLeadScoringService $scorer;

    public function __construct(
        AgentAiLeadIntentDetector $detector,
        AgentAiLeadScoringService $scorer
    ) {
        $this->detector = $detector;
        $this->scorer   = $scorer;
    }

    /**
     * Determine whether the current turn should trigger a contact-capture
     * prompt for anonymous visitors.
     *
     * @param  string      $signal     Detected signal for this turn (nullable).
     * @param  int|null    $visitorUserId  Authenticated visitor user ID if known.
     * @return bool
     */
    public function shouldPromptForContact(?string $signal, ?int $visitorUserId): bool
    {
        if ($visitorUserId !== null) {
            return false;
        }

        return $this->detector->isHighIntent($signal);
    }

    /**
     * Upsert the lead record for a session.
     *
     * Merges $data into the existing lead record (create or update by session_id).
     * questions_asked is appended (not replaced).
     *
     * @param  int   $sessionId
     * @param  array $data       Partial lead fields to set/merge.
     * @return AgentAiChatLead
     */
    public function recordOrUpdate(int $sessionId, array $data): AgentAiChatLead
    {
        $lead = AgentAiChatLead::where('session_id', $sessionId)->first();

        if ($lead === null) {
            $session = AgentAiChatSession::findOrFail($sessionId);
            $lead    = new AgentAiChatLead();
            $lead->session_id = $sessionId;
            $lead->agent_id   = (int) $session->agent_id;

            // Auto-attach session's visitor identity if set.
            if ($session->visitor_user_id !== null) {
                $lead->visitor_user_id = $session->visitor_user_id;
                $this->hydrateFromUser($lead, (int) $session->visitor_user_id);
            }
        }

        // Merge questions_asked.
        $newQuestion = $data['question'] ?? null;
        unset($data['question']);

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $lead->$key = $value;
            }
        }

        if ($newQuestion !== null) {
            $existing           = $lead->questions_asked ?? [];
            $existing[]         = $newQuestion;
            $lead->questions_asked = $existing;
        }

        $lead->save();

        return $lead;
    }

    /**
     * Finalize a lead record after session end or when score >= 50.
     * Sets conversation_summary and recommended_follow_up if not already set.
     *
     * @param  int    $sessionId
     * @param  string $summary
     * @param  string $followUp
     * @return AgentAiChatLead|null
     */
    public function finalize(int $sessionId, string $summary, string $followUp): ?AgentAiChatLead
    {
        $lead = AgentAiChatLead::where('session_id', $sessionId)->first();

        if ($lead === null) {
            Log::warning('AgentAiLeadCaptureService::finalize — no lead found for session', [
                'session_id' => $sessionId,
            ]);
            return null;
        }

        if (empty($lead->conversation_summary)) {
            $lead->conversation_summary = $summary;
        }

        if (empty($lead->recommended_follow_up)) {
            $lead->recommended_follow_up = $followUp;
        }

        $lead->save();

        return $lead;
    }

    /**
     * Hydrate visitor name/email from an authenticated user record.
     */
    private function hydrateFromUser(AgentAiChatLead $lead, int $userId): void
    {
        $user = User::find($userId);

        if ($user === null) {
            return;
        }

        if (empty($lead->visitor_name)) {
            $lead->visitor_name = trim($user->first_name . ' ' . $user->last_name);
        }

        if (empty($lead->visitor_email)) {
            $lead->visitor_email = $user->email;
        }
    }
}
