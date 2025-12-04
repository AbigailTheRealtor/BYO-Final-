<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BidSubmittedNotification extends Notification implements ShouldBroadcast
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
            'message' => "New bid submitted by user " .
                ($this->bid->user->first_name ?? 'Unknown') . ' ' .
                ($this->bid->user->last_name ?? 'User') .
                " for your listing " .
                ($this->auction->title ?? '') . "!",
            'bid_id' => $this->bid->id,
            'auction_id' => $this->auction->id,
            'type' => 'bid_submitted',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'bid_submitted',
            'read_at' => null,
            'created_at' => now()->toDateTimeString(),
            'data' => [
                'message' => "New bid submitted by user " .
                    ($this->bid->user->first_name ?? 'Unknown') . ' ' .
                    ($this->bid->user->last_name ?? 'User') .
                    " for your listing " .
                    ($this->auction->title ?? '') . "!",
                'bid_id' => $this->bid->id,
                'auction_id' => $this->auction->id,
                'created_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->auction->user_id);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }
}
