<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class CounterBidAcceptedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $counterBid;
    public $auction;

    public function __construct($counterBid, $auction)
    {
        $this->counterBid = $counterBid;
        $this->auction = $auction;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Your counter bid for the listing \"" . ($this->auction->title ?? '') . "\" has been accepted!",
            'counter_bid_id' => $this->counterBid->id,
            'auction_id' => $this->auction->id,
            'type' => 'counter_bid_accepted',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'counter_bid_accepted',
            'data' => [
                'message' => "Your counter bid for the listing \"" . ($this->auction->title ?? '') . "\" has been accepted!",
                'counter_bid_id' => $this->counterBid->id,
                'auction_id' => $this->auction->id,
            ],
            'created_at' => now(),
        ]);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->counterBid->user_id);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }
}
