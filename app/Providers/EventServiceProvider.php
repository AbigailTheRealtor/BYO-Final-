<?php

namespace App\Providers;

use App\Events\SellerPropertyAuctionBid;
use App\Events\SellerPropertyAuctionCreated;
use App\Events\SellerPropertyAuctionUpdated;
use App\Listeners\SendSellerPropertyAuctionBidEmail;
use App\Listeners\SendSellerPropertyAuctionEmail;
use App\Listeners\SendSellerPropertyAuctionUpdateEmail;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        User::observe(UserObserver::class);
        Event::listen(
            SellerPropertyAuctionCreated::class,
            [SendSellerPropertyAuctionEmail::class, 'handle']
        );
        Event::listen(function (SellerPropertyAuctionCreated $event) {
            //
        });

        Event::listen(
            SellerPropertyAuctionUpdated::class,
            [SendSellerPropertyAuctionUpdateEmail::class, 'handle']
        );
        Event::listen(function (SellerPropertyAuctionUpdated $event) {
            //
        });

        Event::listen(
            SellerPropertyAuctionBid::class,
            [SendSellerPropertyAuctionBidEmail::class, 'handle']
        );
        Event::listen(function (SellerPropertyAuctionBid $event) {
            //
        });
    }
}
