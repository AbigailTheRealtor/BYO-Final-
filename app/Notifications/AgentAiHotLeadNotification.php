<?php

namespace App\Notifications;

use App\Models\AgentAiChatLead;
use App\Models\AgentAiChatSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * AgentAiHotLeadNotification
 *
 * Persists a dashboard notification card to the `notifications` table when
 * an AI chat session crosses a lead-score threshold.
 *
 * GOVERNANCE:
 *  - Payload must never include bid, compensation, or offer data.
 *  - Only the owning agent receives the notification.
 *  - Visitor contact info is stored only when the lead record supplies it.
 */
class AgentAiHotLeadNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AgentAiChatSession $session,
        private readonly int $score,
        private readonly ?AgentAiChatLead $lead = null
    ) {}

    /**
     * @param  mixed $notifiable
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @param  mixed $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type'          => 'hot_lead',
            'session_id'    => $this->session->id,
            'lead_id'       => $this->lead?->id,
            'lead_score'    => $this->score,
            'lead_type'     => $this->lead?->lead_type,
            'visitor_label' => $this->lead?->visitor_name ?? $this->lead?->visitor_email ?? 'Anonymous',
            'inbox_url'     => '/agent/ai-inbox/' . $this->session->id,
        ];
    }
}
