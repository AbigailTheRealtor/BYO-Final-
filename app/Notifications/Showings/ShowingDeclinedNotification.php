<?php

namespace App\Notifications\Showings;

use App\Models\Showing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowingDeclinedNotification extends Notification
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
            'type'            => 'showing_declined',
            'showing_id'      => $showing->id,
            'listing_address' => $this->listingAddress(),
            'requested_date'  => optional($showing->requested_date)->format('M j, Y'),
            'owner_message'   => $showing->owner_message,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $showing = $this->showing;

        $mail = (new MailMessage)
            ->subject('Your Showing Request Was Declined')
            ->greeting('Hello,')
            ->line('Unfortunately your showing request has been declined.')
            ->line('**Property:** ' . $this->listingAddress());

        if ($showing->owner_message) {
            $mail->line('**Reason:** ' . $showing->owner_message);
        }

        return $mail
            ->action('Browse Other Listings', route('offer.listing.seller.searchListing'))
            ->line('You may request a different time or browse other available properties.');
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
