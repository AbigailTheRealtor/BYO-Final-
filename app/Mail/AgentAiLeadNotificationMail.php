<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * AgentAiLeadNotificationMail
 *
 * Sent to the agent when an AI chat session crosses a score threshold (>= 75).
 *
 * GOVERNANCE:
 *  - Payload must never include bid, compensation, or offer data.
 *  - Visitor contact info is included only in the agent's own notification.
 *  - No cross-agent data exposure.
 */
class AgentAiLeadNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build(): static
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), 'Bid Your Offer')
            ->subject($this->data['subject'] ?? 'AI Inbox Alert: Hot Lead')
            ->view('mail.agent-ai-lead-notification')
            ->with($this->data);
    }
}
