<?php

namespace App\Listeners;

use App\Events\SellerPropertyAuctionCreated;
use App\Mail\NotificationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSellerPropertyAuctionEmail
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
     * @param  \App\Events\SellerPropertyAuctionCreated  $event
     * @return void
     */
    public function handle(SellerPropertyAuctionCreated $event)
    {
        $auction = $event->auction;
        $mail_data['msg'] = "Hi {$auction->user->name}, Property Auction Added successfully.";
        $mail_data['user'] = $auction->user;
        $mail_data['title'] = 'New Seller Property Auction';
        $mail_data['subject'] = 'New Seller Property Auction';
        // return view('mail.notify', $mail_data);
        try {
            Mail::to($auction->user)->send(new NotificationEmail($mail_data));
        } catch (\Throwable $e) {
            Log::error('SendSellerPropertyAuctionEmail failed', ['auction_id' => $auction->id ?? null, 'error' => $e->getMessage()]);
        }
        // dd($event->auction->toArray());
    }
}
