<?php

namespace Tests\Feature\Realtime;

use App\Providers\BroadcastServiceProvider;
use Tests\TestCase;

/**
 * The server half of the same switch.
 *
 * BroadcastServiceProvider was commented out of config/app.php, so Broadcast::routes()
 * never ran, routes/channels.php was never loaded, and /broadcasting/auth was a 404. That
 * went unnoticed only because the browser never got as far as asking.
 *
 * It is registered now and gated on the same flag the browser reads, so the flag is not a
 * trap: with realtime off nothing is added to the routing table, and with realtime on the
 * auth endpoint and the channel rules arrive together.
 */
class BroadcastAuthEndpointTest extends TestCase
{
    private const AUTH_URI = 'broadcasting/auth';

    /** A complete, working realtime configuration — both halves of it. */
    private function configureWorkingRealtime(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.client.enabled' => true,
            'broadcasting.client.key' => 'auth-route-test-key',
            'broadcasting.client.cluster' => 'mt1',
            'broadcasting.connections.pusher.key' => 'auth-route-test-key',
            'broadcasting.connections.pusher.secret' => 'auth-route-test-secret',
            'broadcasting.connections.pusher.app_id' => 'auth-route-test-app-id',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
        ]);
    }

    private function registeredUris(): array
    {
        return collect($this->app['router']->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->all();
    }

    /** @test */
    public function the_provider_is_registered_in_the_application_provider_list(): void
    {
        $this->assertContains(
            BroadcastServiceProvider::class,
            config('app.providers'),
            'The provider must be registered, or turning realtime on cannot register its auth route.'
        );
    }

    /** @test */
    public function no_broadcast_auth_route_exists_while_realtime_is_off(): void
    {
        // This is the shipped state. Uncommenting the provider must not have added surface.
        $this->assertFalse(config('broadcasting.client.enabled'));
        $this->assertNotContains(self::AUTH_URI, $this->registeredUris());
    }

    /** @test */
    public function the_endpoint_is_not_routable_over_http_while_realtime_is_off(): void
    {
        $this->post('/'.self::AUTH_URI)->assertNotFound();
    }

    /** @test */
    public function booting_the_provider_with_realtime_on_registers_the_auth_route(): void
    {
        $this->configureWorkingRealtime();

        (new BroadcastServiceProvider($this->app))->boot();

        $this->assertContains(
            self::AUTH_URI,
            $this->registeredUris(),
            'With realtime on, a private-channel subscription must have an endpoint to authorize against.'
        );
    }

    /**
     * @test
     *
     * The route is only half of it: without routes/channels.php the endpoint refuses every
     * subscription, which looks identical to a permissions bug. Both must arrive together.
     */
    public function booting_the_provider_with_realtime_on_also_loads_the_channel_authorization_rules(): void
    {
        $this->configureWorkingRealtime();

        (new BroadcastServiceProvider($this->app))->boot();

        $broadcaster = $this->app->make(\Illuminate\Contracts\Broadcasting\Broadcaster::class);

        $channels = (function () {
            return $this->channels ?? [];
        })->call($broadcaster);

        $this->assertArrayHasKey(
            'user.{userId}',
            $channels,
            'routes/channels.php defines the only channel the client subscribes to.'
        );
    }
}
