<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OfferWithdrawnNotification extends Notification
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
            ? 'Your offer was withdrawn.'
            : 'An offer was withdrawn.';

        return [
            'message'            => $message,
            'offer_id'           => $this->offer->id,
            'status'             => $this->offer->status,
            'link'               => route('offers.show', $this->offer),
            'type'               => 'offer_withdrawn',
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
                ->subject('Offer #' . $this->offer->id . ' Withdrawn')
                ->line('Your offer (ID: ' . $this->offer->id . ') has been withdrawn.')
                ->line('Current status: ' . $this->offer->status)
                ->action('View Offer', route('offers.show', $this->offer));
        }

        return (new MailMessage)
            ->subject('An Offer on Your Listing Was Withdrawn')
            ->line('An offer (ID: ' . $this->offer->id . ') on your listing has been withdrawn.')
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
