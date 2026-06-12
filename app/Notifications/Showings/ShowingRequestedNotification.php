<?php

namespace App\Notifications\Showings;

use App\Models\Showing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShowingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public Showing $showing) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $showing   = $this->showing;
        $requester = $showing->requester;

        $address = $this->listingAddress();
        $date    = optional($showing->requested_date)->format('M j, Y');

        return [
            'message'         => 'New showing request received.',
            'context_line'    => $date ? $address . ' • ' . $date : $address,
            'type'            => 'showing_requested',
            'showing_id'      => $showing->id,
            'listing_address' => $address,
            'requester_name'  => trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? '')),
            'requested_date'  => $date,
            'requested_start' => $showing->requested_start_time,
            'requested_end'   => $showing->requested_end_time,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $showing       = $this->showing;
        $requester     = $showing->requester;
        $requesterName = trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? '')) ?: 'A user';
        $date          = optional($showing->requested_date)->format('M j, Y') ?? 'TBD';

        return (new MailMessage)
            ->subject('New Showing Request')
            ->greeting('Hello!')
            ->line("{$requesterName} has requested a showing for your listing.")
            ->line('**Property:** ' . $this->listingAddress())
            ->line('**Requested Date:** ' . $date)
            ->when($showing->requested_start_time && $showing->requested_end_time, function ($mail) use ($showing) {
                return $mail->line('**Time:** ' . $this->formatTime($showing->requested_start_time) . ' – ' . $this->formatTime($showing->requested_end_time));
            })
            ->when($showing->requester_message, function ($mail) use ($showing) {
                return $mail->line('**Message:** ' . $showing->requester_message);
            })
            ->action('Manage Showing Requests', route('showings.manage'))
            ->line('Please log in to approve or decline this request.');
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
