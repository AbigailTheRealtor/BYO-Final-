<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;


class BidRejectedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $bid;
    public $auction;

    public function __construct($bid, $auction)
    {
        $this->bid = $bid;
        $this->auction = $auction;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Your bid for the listing \"" . ($this->auction->title ?? '') . "\" has been rejected.",
            'bid_id' => $this->bid->id,
            'auction_id' => $this->auction->id,
            'type' => 'bid_rejected',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'bid_rejected',
            'data' => [
                'message' => "Your bid for the listing \"" . ($this->auction->title ?? '') . "\" has been rejected.",
                'bid_id' => $this->bid->id,
                'auction_id' => $this->auction->id,
            ],
            'created_at' => now(),
        ]);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->bid->user_id);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }
}
