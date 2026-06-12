<?php

namespace App\Notifications\Offers;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OfferSubmittedNotification extends Notification
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
            'message'  => 'New offer received on your listing.',
            'offer_id' => $this->offer->id,
            'status'   => $this->offer->status,
            'link'     => route('offers.show', $this->offer),
            'type'     => 'offer_submitted',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Offer #' . $this->offer->id . ' Submitted')
            ->line('Your offer (ID: ' . $this->offer->id . ') has been submitted successfully.')
            ->line('Current status: ' . $this->offer->status)
            ->action('View Offer', route('offers.show', $this->offer));
    }
}
