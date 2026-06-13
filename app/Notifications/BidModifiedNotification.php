<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasRecipientContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BidModifiedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;
    use HasRecipientContext;

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
        $listingId = $this->auction->listing_id ?? ('TAA-' . $this->auction->id);

        $context = $this->resolveRecipientContext(
            $notifiable,
            $this->bid->user_id ?? null,
            $this->auction->user_id ?? null,
        );

        $message = $context === 'submitter'
            ? 'Your bid was updated.'
            : 'A bid was updated.';

        return [
            'message'           => $message,
            'context_line'      => $this->auction->title ?? '',
            'bid_id'            => $this->bid->id,
            'auction_id'        => $this->auction->id,
            'listing_id'        => $listingId,
            'type'              => 'bid_modified',
            'recipient_context' => $context,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id'   => $this->id,
            'type' => 'bid_modified',
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
