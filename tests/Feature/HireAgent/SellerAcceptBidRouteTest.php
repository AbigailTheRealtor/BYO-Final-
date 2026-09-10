<?php

namespace Tests\Feature\HireAgent;

use App\Http\Middleware\AgentAuth;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Seller Hire Agent accept — the ROUTE contract.
 *
 * ── WHAT WAS WRONG ──────────────────────────────────────────────────────────
 *
 * `POST hire/agent/seller/bid/accept` was registered TWICE in routes/web.php:
 * once beside the other Seller hire-agent routes inside `auth` + `verified`
 * (correct), and again ~280 lines later inside `Route::middleware('agentAuth')`.
 *
 * A later registration of the same method + URI REPLACES the earlier one in the
 * RouteCollection, so the agentAuth copy was the only effective route. That made
 * the endpoint unreachable for every caller, in both directions:
 *
 *   * `AgentAuth` redirects any account whose `user_type` is not `agent`, so the
 *     Seller — the person the form is rendered for — never reached the action;
 *   * `SellerAgentAuctionController::acceptSABid()` aborts 403 unless the caller
 *     owns the listing, so an agent who DID satisfy AgentAuth was refused there.
 *
 * Five Seller-facing views post to `route('acceptSABid')`. None of them worked.
 *
 * ── WHY THE DUPLICATE WAS INVISIBLE ─────────────────────────────────────────
 *
 * RouteCollection is keyed by method + domain + URI, so it SILENTLY overwrites.
 * `Route::getRoutes()` therefore shows one route and reveals nothing about how
 * many registrations produced it — which is exactly why this survived. Detecting
 * it requires re-evaluating the route files against a collection that records
 * every `add()` rather than only the survivors; `rawRegistrations()` below does
 * that, and it is the only way this class of defect can be asserted at all.
 */
class SellerAcceptBidRouteTest extends TestCase
{
    /**
     * Method + URI pairs that are registered more than once on `main` for reasons
     * unrelated to Seller bid acceptance, and which this branch deliberately does
     * NOT fix — fixing them is separate work with its own authorization analysis.
     *
     * This list exists so the duplicate contract can be enforced without forcing
     * unrelated legacy defects into this change. Entries may be REMOVED as they
     * are fixed; an entry may only be ADDED with a stated reason.
     *
     *   * `buyer/counter-terms/{id}` — `Route::any()` registered twice inside the
     *     buyer prefix (CounteredTerms then BuyerCounteredTermsController), which
     *     is why it appears once per HTTP verb.
     *   * `POST landlord/auction/bid/{id}` — two registrations in the landlord
     *     property-auction area.
     */
    private const KNOWN_UNRELATED_DUPLICATES = [
        'GET buyer/counter-terms/{id}',
        'HEAD buyer/counter-terms/{id}',
        'POST buyer/counter-terms/{id}',
        'PUT buyer/counter-terms/{id}',
        'PATCH buyer/counter-terms/{id}',
        'DELETE buyer/counter-terms/{id}',
        'OPTIONS buyer/counter-terms/{id}',
        'POST landlord/auction/bid/{id}',
    ];

    /** Every Hire Agent accept/hire endpoint, across all four roles. */
    private const HIRE_AGENT_ACCEPT_ENDPOINTS = [
        'POST hire/agent/seller/bid/accept',
        'POST buyer/hire/agent/auction/bid/accept',
        'POST landlord/hire/agent/auction/bid/accept',
        'POST tenant/hire/agent/auction/bid/accept',
    ];

    // -------------------------------------------------------------------------
    // The effective route
    // -------------------------------------------------------------------------

    public function test_seller_accept_route_resolves_to_the_seller_controller_action(): void
    {
        $route = $this->sellerAcceptRoute();

        $this->assertSame('hire/agent/seller/bid/accept', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertSame(
            'App\Http\Controllers\SellerAgentAuctionController@acceptSABid',
            $route->getActionName()
        );
    }

    public function test_seller_accept_route_is_authenticated_and_verified(): void
    {
        $middleware = $this->sellerAcceptRoute()->gatherMiddleware();

        $this->assertContains('auth', $middleware, 'the accept route must not become public');
        $this->assertContains('verified', $middleware, 'email verification is the current contract');
    }

    /**
     * The regression itself. Asserted on the RESOLVED middleware classes as well as
     * the aliases, so applying `AgentAuth::class` directly is caught too.
     */
    public function test_seller_accept_route_does_not_require_agent_auth(): void
    {
        $route = $this->sellerAcceptRoute();

        $this->assertNotContains(
            'agentAuth',
            $route->gatherMiddleware(),
            'the Seller/listing owner accepts a Hire Agent bid, not the bidding agent'
        );

        $this->assertNotContains(
            AgentAuth::class,
            $this->resolvedMiddleware($route),
            'AgentAuth must not reach this route under any spelling'
        );
    }

    // -------------------------------------------------------------------------
    // Registration uniqueness
    // -------------------------------------------------------------------------

    public function test_seller_accept_route_is_registered_exactly_once(): void
    {
        $counts = $this->rawRegistrations();

        $this->assertSame(
            1,
            $counts['POST hire/agent/seller/bid/accept'] ?? 0,
            'a second registration of this method + URI silently replaces the first — '
            .'that is what made Seller bid acceptance unreachable'
        );
    }

    public function test_no_hire_agent_accept_endpoint_is_registered_twice(): void
    {
        $counts = $this->rawRegistrations();

        foreach (self::HIRE_AGENT_ACCEPT_ENDPOINTS as $endpoint) {
            $this->assertSame(
                1,
                $counts[$endpoint] ?? 0,
                "{$endpoint} must be registered exactly once"
            );
        }
    }

    /**
     * Guards the defect CLASS, while leaving the already-known unrelated legacy
     * duplicates exactly as they are. A NEW duplicate anywhere fails this.
     */
    public function test_no_new_duplicate_route_registrations_are_introduced(): void
    {
        $duplicates = array_keys(array_filter(
            $this->rawRegistrations(),
            fn (int $count) => $count > 1
        ));

        $unexpected = array_values(array_diff($duplicates, self::KNOWN_UNRELATED_DUPLICATES));

        $this->assertSame([], $unexpected, sprintf(
            "these method + URI pairs are registered more than once; the later registration ".
            "silently replaces the earlier one:\n  %s",
            implode("\n  ", $unexpected)
        ));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sellerAcceptRoute(): RoutingRoute
    {
        $route = Route::getRoutes()->getByName('acceptSABid');

        $this->assertNotNull($route, 'the acceptSABid route is missing entirely');

        return $route;
    }

    /**
     * Expand the route's middleware aliases and groups into concrete class names, so
     * an assertion cannot be satisfied merely by spelling the middleware differently.
     *
     * @return array<int, string>
     */
    private function resolvedMiddleware(RoutingRoute $route): array
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernelContract::class);

        $resolved = [];

        foreach ($route->gatherMiddleware() as $name) {
            foreach ((array) MiddlewareNameResolver::resolve(
                $name,
                $kernel->getRouteMiddleware(),
                $kernel->getMiddlewareGroups()
            ) as $class) {
                $resolved[] = is_string($class) ? explode(':', $class, 2)[0] : $class;
            }
        }

        return $resolved;
    }

    /**
     * Re-evaluate the route files against a RouteCollection that records EVERY
     * `add()`, and return "METHOD uri" => registration count.
     *
     * The application's own collection cannot answer this: it is keyed by
     * method + domain + URI and keeps only the last registration.
     *
     * @return array<string, int>
     */
    private function rawRegistrations(): array
    {
        $collection = new class extends RouteCollection {
            /** @var array<int, string> */
            public array $raw = [];

            public function add(RoutingRoute $route)
            {
                foreach ($route->methods() as $method) {
                    $this->raw[] = $method.' '.$route->uri();
                }

                return parent::add($route);
            }
        };

        $recorder = new Router($this->app->make('events'), $this->app);

        $routesProperty = new \ReflectionProperty(Router::class, 'routes');
        $routesProperty->setAccessible(true);
        $routesProperty->setValue($recorder, $collection);

        $original = Route::getFacadeRoot();
        Route::swap($recorder);

        try {
            // `web` mirrors RouteServiceProvider so the recorded URIs match the
            // application's; the middleware itself is irrelevant to the count.
            $recorder->middleware('web')->group(base_path('routes/web.php'));
        } finally {
            Route::swap($original);
            // setRoutes()/the facade swap can leave the container's `routes`
            // binding pointing at the recorder; put the real collection back.
            $this->app->instance('routes', $original->getRoutes());
        }

        return array_count_values($collection->raw);
    }
}
