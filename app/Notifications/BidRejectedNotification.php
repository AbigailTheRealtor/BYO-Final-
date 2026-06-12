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
    public $auctionType;

    public function __construct($bid, $auction, $auctionType = 'tenant_agent')
    {
        $this->bid = $bid;
        $this->auction = $auction;
        $this->auctionType = $auctionType;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message'      => 'Your bid was rejected.',
            'context_line' => $this->auction->title ?? '',
            'bid_id'       => $this->bid->id,
            'auction_id'   => $this->auction->id,
            'type'         => 'bid_rejected',
            'auction_type' => $this->auctionType,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => 'bid_rejected',
            'data'       => $this->toDatabase($notifiable),
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
