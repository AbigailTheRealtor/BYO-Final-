<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;
use App\Models\AcceptedBidSummary;

class BidAcceptedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $bid;
    public $auction;
    public $summaryId;
    public $auctionType;

    public function __construct($bid, $auction, $summaryId = null, $auctionType = 'tenant_agent')
    {
        $this->bid = $bid;
        $this->auction = $auction;
        $this->summaryId = $summaryId;
        $this->auctionType = $auctionType;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        $data = [
            'message'      => 'Your bid was accepted.',
            'context_line' => $this->auction->title ?? '',
            'bid_id'       => $this->bid->id,
            'auction_id'   => $this->auction->id,
            'type'         => 'bid_accepted',
            'auction_type' => $this->auctionType,
        ];
        
        if ($this->summaryId) {
            $data['summary_id'] = $this->summaryId;
            $data['summary_link'] = route('accepted-bid-summary.view', $this->summaryId);
        }
        
        return $data;
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => 'bid_accepted',
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
