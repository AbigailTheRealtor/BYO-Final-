<?php

namespace App\Listeners;

use App\Events\SellerPropertyAuctionBid;
use App\Mail\NotificationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendSellerPropertyAuctionBidEmail
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
     * @param  \App\Events\SellerPropertyAuctionBid  $event
     * @return void
     */

    public function handle(SellerPropertyAuctionBid $event)
    {
        $bid = $event->bid;
        $user = $bid->auction->user;
        $mail_data['msg'] = "Hi {$user->name}, You have new bid on Seller Property Auction - {$bid->auction->address}";
        $mail_data['user'] = $user;
        $mail_data['title'] = 'New Bid on Seller Property Auction';
        $mail_data['subject'] = 'New Bid on Seller Property Auction';
        // return view('mail.notify', $mail_data);
        Mail::to($user)->send(new NotificationEmail($mail_data));
    }
}
