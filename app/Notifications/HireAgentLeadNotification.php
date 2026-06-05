<?php

namespace App\Notifications;

use App\Models\HireAgentLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HireAgentLeadNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public HireAgentLead $lead;

    /** @var array{agent_id:int,preset_id:int,agent_name:string,preset_name:string,match_type:string,service_count:int}|null */
    public ?array $matchedPresetEntry;

    public function __construct(HireAgentLead $lead, ?array $matchedPresetEntry = null)
    {
        $this->lead              = $lead;
        $this->matchedPresetEntry = $matchedPresetEntry;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    // ── Database channel ──────────────────────────────────────────────────

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    // ── Broadcast channel ─────────────────────────────────────────────────

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel(
                'App.Models.User.' . $this->lead->target_agent_id
            ),
        ];
    }

    public function broadcastType(): string
    {
        return 'HireAgentLeadReceived';
    }

    // ── Mail (add 'mail' to via() when mail is configured) ────────────────

    public function toMail(object $notifiable): MailMessage
    {
        $lead       = $this->lead;
        $presetName = $this->matchedPresetEntry['preset_name'] ?? null;

        return (new MailMessage)
            ->subject('New Hire Agent Lead — ' . $lead->representationTypeLabel())
            ->greeting('Hello ' . ($notifiable->user_name ?? 'Agent') . ',')
            ->line('A visitor has requested your services as a **' . $lead->representationTypeLabel() . '**.')
            ->line('**Property type:** ' . $lead->selectedPropertyTypeLabel())
            ->when($presetName, fn($m) => $m->line('**Matched preset:** ' . $presetName))
            ->line('**Requester:** ' . $lead->requester_name . ' (' . $lead->requester_email . ')')
            ->when($lead->requester_phone, fn($m) => $m->line('**Phone:** ' . $lead->requester_phone))
            ->when($lead->message, fn($m) => $m->line('**Message:** ' . $lead->message))
            ->when($lead->source_listing_title, fn($m) => $m->line('**Listing:** ' . $lead->source_listing_title))
            ->action('View Lead', route('agent.hire-leads.show', $lead->id))
            ->line('Please respond promptly to help this client.');
    }

    // ── Shared payload ────────────────────────────────────────────────────

    private function payload(): array
    {
        $lead       = $this->lead;
        $presetName = $this->matchedPresetEntry['preset_name'] ?? null;

        return [
            'type'                       => 'hire_agent_lead',
            'lead_id'                    => $lead->id,
            // Source attribution
            'source_listing_type'        => $lead->source_listing_type,
            'source_listing_type_label'  => $lead->sourceListingTypeLabel(),
            'source_listing_id'          => $lead->source_listing_id,
            'source_listing_role'        => $lead->source_listing_role,
            'source_property_type'       => $lead->source_property_type,
            'lead_source'                => $lead->lead_source,
            // Snapshot
            'source_listing_title'       => $lead->source_listing_title,
            'source_listing_url'         => $lead->source_listing_url,
            // Requester selections
            'representation_type'        => $lead->representation_type,
            'representation_type_label'  => $lead->representationTypeLabel(),
            'selected_property_type'     => $lead->selected_property_type,
            'selected_property_label'    => $lead->selectedPropertyTypeLabel(),
            // Requester contact
            'requester_name'             => $lead->requester_name,
            'requester_email'            => $lead->requester_email,
            'requester_phone'            => $lead->requester_phone,
            // Preset match
            'preset_match_status'        => $lead->preset_match_status,
            'preset_match_label'         => $lead->presetMatchStatusLabel(),
            'matched_preset_name'        => $presetName,
            // Status + deep link
            'status'                     => $lead->status,
            'message_preview'            => $lead->message ? \Str::limit($lead->message, 120) : null,
            'deep_link'                  => route('agent.hire-leads.show', $lead->id),
            'created_at'                 => $lead->created_at?->toIso8601String(),
        ];
    }
}
