<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OfferExpiredNotification extends Notification
{
    use Queueable;
    use HasRecipientContext;

    public function __construct(public readonly Offer $offer)
    {
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->offer->user_id,
            $this->offer->relationLoaded('offerAuction')
                ? ($this->offer->offerAuction?->user_id ?? null)
                : null,
        );

        $message = $context === 'submitter'
            ? 'Action is needed on your offer.'
            : 'Action is needed on an offer.';

        return [
            'message'            => $message,
            'offer_id'           => $this->offer->id,
            'status'             => $this->offer->status,
            'link'               => route('offers.show', $this->offer),
            'type'               => 'offer_expired',
            'recipient_context'  => $context,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->offer->user_id,
            $this->offer->relationLoaded('offerAuction')
                ? ($this->offer->offerAuction?->user_id ?? null)
                : null,
        );

        if ($context === 'submitter') {
            return (new MailMessage)
                ->subject('Action Needed on Your Offer #' . $this->offer->id)
                ->line('Action is needed on your offer (ID: ' . $this->offer->id . ').')
                ->line('Current status: ' . $this->offer->status)
                ->action('View Offer', route('offers.show', $this->offer));
        }

        return (new MailMessage)
            ->subject('Action Needed on an Offer — #' . $this->offer->id)
            ->line('Action is needed on an offer (ID: ' . $this->offer->id . ') on your listing.')
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
