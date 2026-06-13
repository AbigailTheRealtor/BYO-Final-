<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class CounterBidSubmittedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;
    use HasRecipientContext;

    public $bid;
    public $auction;
    public $sender;
    public $recipientId; // needed because broadcastOn() has no $notifiable
    public $auctionType;

    public function __construct($bid, $auction, $sender, $recipientId, $auctionType = null)
    {
        $this->bid = $bid;
        $this->auction = $auction;
        $this->sender = $sender;
        $this->recipientId = $recipientId;
        $this->auctionType = $auctionType;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        $senderId = is_object($this->sender) ? ($this->sender->id ?? null) : null;
        $ownerId  = $this->auction->user_id ?? null;

        $context = $this->resolveRecipientContext($notifiable, $senderId, $ownerId);

        $message = $context === 'submitter'
            ? 'Your counter bid was submitted.'
            : 'You received a counter bid.';

        return [
            'message'           => $message,
            'context_line'      => $this->auction->title ?? '',
            'bid_id'            => $this->bid->id,
            'auction_id'        => $this->auction->id,
            'type'              => 'counter_bid_submitted',
            'auction_type'      => $this->auctionType,
            'recipient_context' => $context,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'type'       => 'counter_bid_submitted',
            'data'       => $this->toDatabase($notifiable),
            'created_at' => now(),
        ]);
    }

    /**
     * IMPORTANT:
     * No arguments allowed here. Must match parent method.
     * We use saved $recipientId for channel identification.
     */
    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->recipientId);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }
}
