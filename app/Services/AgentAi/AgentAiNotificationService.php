<?php

namespace App\Services\AgentAi;

use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatSession;
use App\Models\User;
use App\Notifications\AgentAiHotLeadNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * AgentAiNotificationService
 *
 * Fires threshold-based notifications to agents when a lead reaches a scoring
 * milestone. Each threshold is emitted at most once per session via deduplication
 * timestamps on agent_ai_chat_sessions.
 *
 * Thresholds (from config/ask_ai.php):
 *   score >= 50 → dashboard notification card
 *   score >= 75 → dashboard card + email to agent
 *   score >= 90 → dashboard card + email + in-app nav badge
 *
 * GOVERNANCE:
 *  - Emails use the existing NotificationEmail / mail.notify system.
 *  - No external API calls beyond Laravel's built-in mail channel.
 *  - No bid, compensation, or offer data in notification payloads.
 *  - Each threshold fires at most once per session (deduplication via timestamps).
 */
class AgentAiNotificationService
{
    private AgentAiLeadScoringService $scorer;

    public function __construct(AgentAiLeadScoringService $scorer)
    {
        $this->scorer = $scorer;
    }

    /**
     * Check current session score against all thresholds and fire notifications
     * that have not yet been emitted for this session.
     *
     * @param  AgentAiChatSession $session
     * @param  int                $currentScore  Running score for the session.
     * @param  AgentAiChatLead|null $lead        Optional lead record for payload enrichment.
     * @return array  List of threshold names that were newly triggered this call.
     */
    public function checkAndNotify(
        AgentAiChatSession $session,
        int                $currentScore,
        ?AgentAiChatLead   $lead = null
    ): array {
        $thresholds = $this->scorer->thresholds();
        $triggered  = [];

        $cardThreshold  = (int) ($thresholds['dashboard_card'] ?? 50);
        $emailThreshold = (int) ($thresholds['email']          ?? 75);
        $badgeThreshold = (int) ($thresholds['nav_badge']      ?? 90);

        // ── Threshold: dashboard card (score >= 50) ─────────────────────────
        if ($currentScore >= $cardThreshold && $session->notified_score_50_at === null) {
            $this->emitDashboardCard($session, $currentScore, $lead);
            $session->notified_score_50_at = now();
            $triggered[] = 'dashboard_card';
        }

        // ── Threshold: email (score >= 75) ──────────────────────────────────
        if ($currentScore >= $emailThreshold && $session->notified_score_75_at === null) {
            if (!in_array('dashboard_card', $triggered, true)) {
                $this->emitDashboardCard($session, $currentScore, $lead);
                $triggered[] = 'dashboard_card';
            }
            $this->emitEmail($session, $currentScore, $lead);
            $session->notified_score_75_at = now();
            $triggered[] = 'email';
        }

        // ── Threshold: nav badge (score >= 90) ─────────────────────────────
        if ($currentScore >= $badgeThreshold && $session->notified_score_90_at === null) {
            if (!in_array('dashboard_card', $triggered, true)) {
                $this->emitDashboardCard($session, $currentScore, $lead);
                $triggered[] = 'dashboard_card';
            }
            if (!in_array('email', $triggered, true)) {
                $this->emitEmail($session, $currentScore, $lead);
                $triggered[] = 'email';
            }
            $this->emitNavBadgeEvent($session);
            $session->notified_score_90_at = now();
            $triggered[] = 'nav_badge';
        }

        // Persist any threshold timestamps set above.
        if (!empty($triggered)) {
            $session->save();
        }

        return $triggered;
    }

    // ── Private emitters ──────────────────────────────────────────────────────

    /**
     * Persist a dashboard notification card via Laravel's database notification
     * channel. The record is written to the `notifications` table and read by
     * the agent's dashboard / inbox badge.
     *
     * The in-app nav badge count is driven by a live DB query (unreviewed
     * high-score leads), not by this notification record; this record provides
     * the concrete persisted dashboard-card artifact.
     */
    private function emitDashboardCard(
        AgentAiChatSession $session,
        int $score,
        ?AgentAiChatLead $lead
    ): void {
        try {
            $agent = User::find($session->agent_id);

            if ($agent === null) {
                Log::warning('AgentAiNotificationService: agent not found for dashboard card', [
                    'agent_id' => $session->agent_id,
                ]);
                return;
            }

            $agent->notify(new AgentAiHotLeadNotification($session, $score, $lead));

            Log::info('AgentAiNotificationService: dashboard card persisted', [
                'agent_id'   => $session->agent_id,
                'session_id' => $session->id,
                'score'      => $score,
                'lead_id'    => $lead?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('AgentAiNotificationService: dashboard card failed', [
                'agent_id'   => $session->agent_id,
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send an email to the agent using the existing NotificationEmail system.
     */
    private function emitEmail(
        AgentAiChatSession $session,
        int $score,
        ?AgentAiChatLead $lead
    ): void {
        try {
            $agent = User::find($session->agent_id);

            if ($agent === null || empty($agent->email)) {
                Log::warning('AgentAiNotificationService: agent not found or no email', [
                    'agent_id' => $session->agent_id,
                ]);
                return;
            }

            $visitorLabel = $lead?->visitor_name ?? $lead?->visitor_email ?? 'A visitor';
            $leadType     = $lead?->lead_type ?? 'unknown';
            $questions    = $lead?->questions_asked ?? [];

            $emailData = [
                'subject'      => "AI Inbox Alert: Hot Lead — Score {$score}",
                'agentName'    => $agent->first_name ?? $agent->name,
                'score'        => $score,
                'visitorLabel' => $visitorLabel,
                'leadType'     => $leadType,
                'questions'    => array_slice($questions, -3),
                'sessionId'    => $session->id,
            ];

            Mail::to($agent->email)->send(
                new \App\Mail\AgentAiLeadNotificationMail($emailData)
            );

            Log::info('AgentAiNotificationService: email sent', [
                'agent_id'   => $session->agent_id,
                'session_id' => $session->id,
                'score'      => $score,
            ]);
        } catch (\Throwable $e) {
            Log::error('AgentAiNotificationService: email failed', [
                'agent_id'   => $session->agent_id,
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Emit an in-app nav badge event (score >= 90).
     *
     * Logged here; the sidenav badge reads live from the DB query
     * (unreviewed hot leads), so no additional persistence is needed.
     */
    private function emitNavBadgeEvent(AgentAiChatSession $session): void
    {
        Log::info('AgentAiNotificationService: nav badge event', [
            'agent_id'   => $session->agent_id,
            'session_id' => $session->id,
        ]);
    }
}
