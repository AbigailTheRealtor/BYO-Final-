<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BidModifiedNotification extends Notification implements ShouldBroadcast
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
        $agentName = ($this->bid->user->first_name ?? 'Unknown') . ' ' . ($this->bid->user->last_name ?? 'Agent');
        $listingId = $this->auction->listing_id ?? ('TAA-' . $this->auction->id);
        
        return [
            'message' => "Agent {$agentName} has modified their bid for listing {$listingId}. Please review the updated terms.",
            'bid_id' => $this->bid->id,
            'auction_id' => $this->auction->id,
            'listing_id' => $listingId,
            'type' => 'bid_modified',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        $agentName = ($this->bid->user->first_name ?? 'Unknown') . ' ' . ($this->bid->user->last_name ?? 'Agent');
        $listingId = $this->auction->listing_id ?? ('TAA-' . $this->auction->id);
        
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'bid_modified',
            'read_at' => null,
            'created_at' => now()->toDateTimeString(),
            'data' => [
                'message' => "Agent {$agentName} has modified their bid for listing {$listingId}. Please review the updated terms.",
                'bid_id' => $this->bid->id,
                'auction_id' => $this->auction->id,
                'listing_id' => $listingId,
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
