<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;
use App\Mail\OfferListingStatusMail;
use App\Models\OfferAuction;

class OfferListingStatusNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public OfferAuction $listing;
    public string $status;

    public function __construct(OfferAuction $listing, string $status)
    {
        $this->listing = $listing;
        $this->status  = $status;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail($notifiable): OfferListingStatusMail
    {
        return (new OfferListingStatusMail($this->listing, $this->status))
            ->to($notifiable->email);
    }

    public function toDatabase($notifiable): array
    {
        $title = $this->listing->title ?? 'your listing';

        if ($this->status === 'approved') {
            $message = "Your offer listing \"{$title}\" has been approved and is now live.";
        } else {
            $message = "Your offer listing \"{$title}\" has been rejected by the admin.";
        }

        return [
            'message'    => $message,
            'listing_id' => $this->listing->id,
            'status'     => $this->status,
            'type'       => 'offer_listing_status',
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => 'offer_listing_status',
            'data'       => $this->toDatabase($notifiable),
            'created_at' => now(),
        ]);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->listing->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
