<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class BidSubmitted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $receiverId;
    public $senderId;
    public $auctionId;

    public function __construct($receiverId, $senderId, $auctionId)
    {
        $this->receiverId = $receiverId;
        $this->senderId = $senderId;
        $this->auctionId = $auctionId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->receiverId);
    }

    public function broadcastAs()
    {
        return 'BidSubmitted';
    }
}
