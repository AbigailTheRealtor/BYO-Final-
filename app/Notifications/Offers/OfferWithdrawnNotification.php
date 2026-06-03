<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OfferWithdrawnNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Offer $offer)
    {
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'offer_id' => $this->offer->id,
            'status'   => $this->offer->status,
            'link'     => route('offers.show', $this->offer),
            'type'     => 'offer_withdrawn',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Offer #' . $this->offer->id . ' Withdrawn')
            ->line('Offer #' . $this->offer->id . ' has been withdrawn.')
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
