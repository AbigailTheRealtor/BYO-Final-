<?php

namespace App\Services\AgentAi;

use App\Models\AgentAiChatMessage;

/**
 * AgentAiLeadScoringService
 *
 * Scores lead engagement based on deterministic, configuration-driven rules.
 * All point values are read from config/ask_ai.php (agent_ai_lead_scoring.points)
 * so thresholds can be tuned without code changes.
 *
 * GOVERNANCE:
 *  - Read-only scoring. No external HTTP calls.
 *  - No DB writes beyond updating lead_score_snapshot on existing message records.
 *  - Notification thresholds must be derived from this service, never from
 *    AI-generated classifications alone.
 */
class AgentAiLeadScoringService
{
    private AgentAiLeadIntentDetector $detector;

    public function __construct(AgentAiLeadIntentDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Return the point value for a single scoring signal.
     *
     * @param  string $signal  One of AgentAiLeadIntentDetector::SIGNAL_* constants.
     * @return int             Points for this signal (0 if signal is unknown).
     */
    public function pointsForSignal(string $signal): int
    {
        $points = config('ask_ai.agent_ai_lead_scoring.points', []);

        return (int) ($points[$signal] ?? 0);
    }

    /**
     * Score a single turn: detect signal from the question, add contact-field
     * bonuses if email/phone were provided in the text.
     *
     * @param  string $question        The user's raw question text.
     * @param  array  $collectedFields Keys already collected ('email','phone').
     * @return array{signal: string|null, points: int}
     */
    public function scoreTurn(string $question, array $collectedFields = []): array
    {
        $signal = $this->detector->detectSignal($question);
        $points = $signal !== null ? $this->pointsForSignal($signal) : 0;

        // Contact-field bonuses — only award once per session (caller tracks collected).
        if (!in_array('email', $collectedFields, true) && $this->detector->containsEmail($question)) {
            $points += $this->pointsForSignal(AgentAiLeadIntentDetector::SIGNAL_EMAIL_PROVIDED);
        }

        if (!in_array('phone', $collectedFields, true) && $this->detector->containsPhone($question)) {
            $points += $this->pointsForSignal(AgentAiLeadIntentDetector::SIGNAL_PHONE_PROVIDED);
        }

        return [
            'signal' => $signal,
            'points' => $points,
        ];
    }

    /**
     * Accumulate the running score for a session by summing lead_score_snapshot
     * values from all user messages that have a snapshot set.
     *
     * Falls back to summing per-turn scores by re-detecting signals when
     * snapshots are absent (e.g. during the current turn before the message
     * is persisted).
     *
     * @param  int $sessionId
     * @return int  Running total, capped at config max_score (default 100).
     */
    public function accumulateForSession(int $sessionId): int
    {
        $messages = AgentAiChatMessage::where('session_id', $sessionId)
            ->where('role', AgentAiChatMessage::ROLE_USER)
            ->orderBy('created_at')
            ->get();

        $total           = 0;
        $collectedFields = [];

        foreach ($messages as $msg) {
            $turn = $this->scoreTurn($msg->content, $collectedFields);
            $total += $turn['points'];

            // Track contact fields collected so bonuses are only awarded once.
            if ($this->detector->containsEmail($msg->content)) {
                $collectedFields[] = 'email';
            }
            if ($this->detector->containsPhone($msg->content)) {
                $collectedFields[] = 'phone';
            }
        }

        $max = (int) config('ask_ai.agent_ai_lead_scoring.max_score', 100);

        return min($total, $max);
    }

    /**
     * Return the three notification thresholds from configuration.
     *
     * @return array{dashboard_card: int, email: int, nav_badge: int}
     */
    public function thresholds(): array
    {
        return (array) config('ask_ai.agent_ai_lead_scoring.thresholds', [
            'dashboard_card' => 50,
            'email'          => 75,
            'nav_badge'      => 90,
        ]);
    }
}
