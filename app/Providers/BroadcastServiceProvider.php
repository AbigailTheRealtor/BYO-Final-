<?php

namespace App\Providers;

use App\Support\Realtime\RealtimeClientConfig;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * Registers /broadcasting/auth and the channel authorization rules — but ONLY while
 * realtime is switched on.
 *
 * This provider was commented out of config/app.php's provider list, so Broadcast::routes()
 * never ran and routes/channels.php was never loaded. /broadcasting/auth was a 404. That
 * was invisible because the browser never got as far as asking: with no realtime
 * configuration on the page there is no private-channel subscription to authorize.
 *
 * It is registered now, and gated on the SAME switch the browser reads, so that the flag
 * is not a trap. Turning BROADCAST_CLIENT_ENABLED on used to buy you a client that
 * subscribes and a server that 404s its auth request; both halves now move together.
 *
 * While realtime is off this provider registers no route and defines no channel, so the
 * routing table is byte-identical to what it was before it was uncommented.
 *
 * Note for whoever enables this: nothing in deploy/ runs `route:cache`, so no cached route
 * table can outlive the flag change. If that ever changes, the cache must be rebuilt when
 * this switch moves.
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! RealtimeClientConfig::enabled()) {
            return;
        }

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
