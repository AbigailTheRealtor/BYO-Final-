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
            'message'      => 'New bid received on your listing.',
            'context_line' => $this->auction->title ?? '',
            'bid_id'       => $this->bid->id,
            'auction_id'   => $this->auction->id,
            'type'         => 'bid_submitted',
            'auction_type' => $this->auctionType,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'   => $this->id,
            'type' => 'bid_submitted',
            'data' => $this->toDatabase($notifiable),
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
