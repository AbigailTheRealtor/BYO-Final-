<?php

namespace App\Listeners;

use App\Events\SellerPropertyAuctionUpdated;
use App\Mail\NotificationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendSellerPropertyAuctionUpdateEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\SellerPropertyAuctionUpdated  $event
     * @return void
     */
    public function handle(SellerPropertyAuctionUpdated $event)
    {
        $auction = $event->auction;
        $mail_data['msg'] = "Hi {$auction->user->name}, Property Auction Updated successfully.";
        $mail_data['user'] = $auction->user;
        $mail_data['title'] = 'Update Seller Property Auction';
        $mail_data['subject'] = 'Update Seller Property Auction';
        Mail::to($auction->user)->send(new NotificationEmail($mail_data));
    }
}
