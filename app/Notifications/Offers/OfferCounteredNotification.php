<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

final class OfferCounteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Offer $parentOffer,
        private readonly Offer $counterOffer,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message'          => 'You received a counter offer.',
            'parent_offer_id'  => $this->parentOffer->id,
            'counter_offer_id' => $this->counterOffer->id,
            'status'           => $this->counterOffer->status,
            'link'             => route('offers.show', $this->counterOffer),
            'type'             => 'offer_countered',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Counter Offer #{$this->counterOffer->id} on Offer #{$this->parentOffer->id} — Status: {$this->counterOffer->status}")
            ->line("A counter offer (#{$this->counterOffer->id}) has been submitted in response to offer #{$this->parentOffer->id}.")
            ->line("Current status: {$this->counterOffer->status}.")
            ->action('View Counter Offer', route('offers.show', $this->counterOffer));
    }
}
