<?php

namespace App\Notifications\Showings;

use App\Models\Showing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowingApprovedNotification extends Notification
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
            'type'            => 'showing_approved',
            'showing_id'      => $showing->id,
            'listing_address' => $this->listingAddress(),
            'approved_date'   => optional($showing->approved_date)->format('M j, Y'),
            'approved_start'  => $showing->approved_start_time,
            'approved_end'    => $showing->approved_end_time,
            'owner_message'   => $showing->owner_message,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $showing = $this->showing;
        $date    = optional($showing->approved_date)->format('M j, Y')
                ?? optional($showing->requested_date)->format('M j, Y')
                ?? 'TBD';

        $mail = (new MailMessage)
            ->subject('Your Showing Request Was Approved')
            ->greeting('Great news!')
            ->line('Your showing request has been approved.')
            ->line('**Property:** ' . $this->listingAddress())
            ->line('**Confirmed Date:** ' . $date);

        $start = $showing->approved_start_time ?? $showing->requested_start_time;
        $end   = $showing->approved_end_time   ?? $showing->requested_end_time;

        if ($start && $end) {
            $mail->line('**Time:** ' . $this->formatTime($start) . ' – ' . $this->formatTime($end));
        }

        if ($showing->owner_message) {
            $mail->line('**Message from owner:** ' . $showing->owner_message);
        }

        return $mail
            ->action('View Showing Details', route('showings.show', $showing))
            ->line('Please be on time for your scheduled showing.');
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

    private function formatTime(string $time): string
    {
        try {
            return \Carbon\Carbon::createFromTimeString($time)->format('g:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }
}
