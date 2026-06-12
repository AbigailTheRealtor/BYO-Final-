<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class CounterBidRejectedNotification extends Notification implements ShouldBroadcast
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
            'message'        => 'Your counter proposal was rejected.',
            'context_line'   => $this->auction->title ?? '',
            'counter_bid_id' => $this->counterBid->id,
            'auction_id'     => $this->auction->id,
            'type'           => 'counter_bid_rejected',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => 'counter_bid_rejected',
            'data'       => $this->toDatabase($notifiable),
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
