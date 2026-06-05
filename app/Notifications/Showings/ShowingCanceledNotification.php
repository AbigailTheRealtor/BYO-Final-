<?php

namespace App\Notifications\Showings;

use App\Models\Showing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowingCanceledNotification extends Notification
{
    use Queueable;

    public function __construct(public Showing $showing) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $showing = $this->showing;

        return [
            'type'            => 'showing_canceled',
            'showing_id'      => $showing->id,
            'listing_address' => $this->listingAddress(),
            'requested_date'  => optional($showing->requested_date)->format('M j, Y'),
            'canceled_at'     => optional($showing->canceled_at)->toDateTimeString(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $showing = $this->showing;
        $date    = optional($showing->approved_date ?? $showing->requested_date)->format('M j, Y') ?? 'TBD';

        return (new MailMessage)
            ->subject('A Showing Was Canceled')
            ->greeting('Hello,')
            ->line('A showing has been canceled.')
            ->line('**Property:** ' . $this->listingAddress())
            ->line('**Originally Scheduled:** ' . $date)
            ->action('View Showings', route('showings.manage'))
            ->line('No further action is required.');
    }

    private function listingAddress(): string
    {
        $auction = $this->showing->offerAuction;
        if (!$auction) {
            return 'Listing #' . $this->showing->offer_auction_id;
        }

        $metas   = $auction->metas;
        $address = $metas->where('meta_key', 'address')->first()?->meta_value ?? '';
        $city    = $metas->where('meta_key', 'property_city')->first()?->meta_value ?? '';
        $state   = $metas->where('meta_key', 'property_state')->first()?->meta_value ?? '';
        $zip     = $metas->where('meta_key', 'property_zip')->first()?->meta_value
                ?? $metas->where('meta_key', 'zip_code')->first()?->meta_value ?? '';

        $parts = array_filter([$address, $city, trim("$state $zip")]);

        return $parts ? implode(', ', $parts) : ('Listing #' . $auction->id);
    }
}
