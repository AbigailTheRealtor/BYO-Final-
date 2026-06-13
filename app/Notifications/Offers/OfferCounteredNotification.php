<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

final class OfferCounteredNotification extends Notification
{
    use Queueable;
    use HasRecipientContext;

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
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->parentOffer->user_id,
            $this->parentOffer->relationLoaded('offerAuction')
                ? ($this->parentOffer->offerAuction?->user_id ?? null)
                : null,
        );

        $message = $context === 'submitter'
            ? 'Your offer received a counter offer.'
            : 'You received a counter offer.';

        return [
            'message'            => $message,
            'parent_offer_id'    => $this->parentOffer->id,
            'counter_offer_id'   => $this->counterOffer->id,
            'status'             => $this->counterOffer->status,
            'link'               => route('offers.show', $this->counterOffer),
            'type'               => 'offer_countered',
            'recipient_context'  => $context,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->parentOffer->user_id,
            $this->parentOffer->relationLoaded('offerAuction')
                ? ($this->parentOffer->offerAuction?->user_id ?? null)
                : null,
        );

        if ($context === 'submitter') {
            return (new MailMessage)
                ->subject("Counter Offer #{$this->counterOffer->id} on Your Offer #{$this->parentOffer->id}")
                ->line("A counter offer (#{$this->counterOffer->id}) has been submitted in response to your offer #{$this->parentOffer->id}.")
                ->line("Current status: {$this->counterOffer->status}.")
                ->action('View Counter Offer', route('offers.show', $this->counterOffer));
        }

        return (new MailMessage)
            ->subject("Counter Offer #{$this->counterOffer->id} on Offer #{$this->parentOffer->id} — Status: {$this->counterOffer->status}")
            ->line("A counter offer (#{$this->counterOffer->id}) has been submitted in response to offer #{$this->parentOffer->id}.")
            ->line("Current status: {$this->counterOffer->status}.")
            ->action('View Counter Offer', route('offers.show', $this->counterOffer));
    }
}
