<?php

namespace App\Providers;

use App\Events\SellerPropertyAuctionBid;
use App\Events\SellerPropertyAuctionCreated;
use App\Events\SellerPropertyAuctionUpdated;
use App\Events\ShowingStatusChanged;
use App\Listeners\SendSellerPropertyAuctionBidEmail;
use App\Listeners\SendSellerPropertyAuctionEmail;
use App\Listeners\SendSellerPropertyAuctionUpdateEmail;
use App\Listeners\ShowingNotificationListener;
use App\Models\AcceptedBidSummary;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use App\Observers\AcceptedBidSummaryAnalyticsObserver;
use App\Observers\AgentBidAnalyticsObserver;
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
        ShowingStatusChanged::class => [
            ShowingNotificationListener::class,
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

        // P7 Matching Analytics: capture score snapshots and funnel timestamps
        // on every agent bid lifecycle event (created/updated/accepted).
        $bidObserver = AgentBidAnalyticsObserver::class;
        SellerAgentAuctionBid::observe($bidObserver);
        BuyerAgentAuctionBid::observe($bidObserver);
        LandlordAgentAuctionBid::observe($bidObserver);
        TenantAgentAuctionBid::observe($bidObserver);

        // 'agent_hired' snapshot fires when AcceptedBidSummary is created.
        AcceptedBidSummary::observe(AcceptedBidSummaryAnalyticsObserver::class);
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
