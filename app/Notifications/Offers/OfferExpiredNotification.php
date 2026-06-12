<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OfferExpiredNotification extends Notification
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
            'message'  => 'An offer has expired.',
            'offer_id' => $this->offer->id,
            'status'   => $this->offer->status,
            'link'     => route('offers.show', $this->offer),
            'type'     => 'offer_expired',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Offer #' . $this->offer->id . ' Expired')
            ->line('Your offer (ID: ' . $this->offer->id . ') has expired.')
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
