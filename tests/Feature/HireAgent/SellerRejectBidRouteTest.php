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
 * Seller Hire Agent reject — the ROUTE contract.
 *
 * ── WHAT WAS WRONG ──────────────────────────────────────────────────────────
 *
 * `POST hire/agent/seller/bid/reject` was registered inside
 * `Route::middleware('agentAuth')`, so `AgentAuth` redirected any account whose
 * `user_type` is not `agent` — which is every Seller. The four Seller-facing
 * views that post to `route('rejectSABid')` could not reach the action at all.
 *
 * This is the sibling of the accept defect fixed in PR #143, but NOT the same
 * shape, and the difference matters for how it is guarded:
 *
 *   * accept was registered TWICE, and the agentAuth copy silently REPLACED the
 *     correct consumer-scoped one in the RouteCollection. Uniqueness was the
 *     detection mechanism there.
 *   * reject had exactly ONE registration, and it was the mis-scoped one. No
 *     duplicate existed, so no uniqueness check could ever have found it — only
 *     an assertion about the effective route's MIDDLEWARE can.
 *
 * Hence the emphasis below: the middleware assertions are the regression, and
 * the uniqueness assertion is there to stop the accept defect's shape being
 * reintroduced on this URI.
 *
 * ── THE AUTHORIZATION POINT ─────────────────────────────────────────────────
 *
 * Rejecting a Seller Hire Agent bid is a decision by the Seller/listing owner
 * about an incoming agent proposal. `SellerAgentAuctionController::rejectSABid()`
 * already enforced exactly that (`abort(403)` unless `$auction->user_id` is the
 * caller) — the controller was correct throughout and is unchanged. Only the
 * route scope was wrong, which is why the fix moves a registration and touches
 * no authorization logic.
 *
 * The end-to-end behaviour is covered by SellerRejectBidFlowTest.
 */
class SellerRejectBidRouteTest extends TestCase
{
    /**
     * Method + URI pairs registered more than once on `main` for reasons unrelated
     * to Seller bid rejection, and which this branch deliberately does NOT fix.
     *
     * Kept identical to SellerAcceptBidRouteTest's list — the two classes ask the
     * same question of the same route files, and a divergence between them would
     * be a bug in the tests rather than a finding about the routes.
     *
     *   * `buyer/counter-terms/{id}` — `Route::any()` registered twice inside the
     *     buyer prefix, which is why it appears once per HTTP verb.
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

    /**
     * Every Hire Agent reject endpoint, across all four roles.
     *
     * Buyer, Landlord and Tenant were already consumer-scoped when this defect was
     * found; Seller was the only one of the four inside `agentAuth`. They are all
     * asserted here so the next role to drift is caught by the same test.
     */
    private const HIRE_AGENT_REJECT_ROUTE_NAMES = [
        'rejectSABid',
        'buyer.hire.agent.auction.bid.reject',
        'landlord.hire.agent.auction.bid.reject',
        'tenant.hire.agent.auction.bid.reject',
    ];

    // -------------------------------------------------------------------------
    // The effective route
    // -------------------------------------------------------------------------

    public function test_seller_reject_route_resolves_to_the_seller_controller_action(): void
    {
        $route = $this->sellerRejectRoute();

        $this->assertSame('hire/agent/seller/bid/reject', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertSame(
            'App\Http\Controllers\SellerAgentAuctionController@rejectSABid',
            $route->getActionName()
        );
    }

    public function test_seller_reject_route_is_authenticated_and_verified(): void
    {
        $middleware = $this->sellerRejectRoute()->gatherMiddleware();

        $this->assertContains('auth', $middleware, 'the reject route must not become public');
        $this->assertContains('verified', $middleware, 'email verification is the current contract');
    }

    /**
     * The regression itself. Asserted on the RESOLVED middleware classes as well as
     * the aliases, so applying `AgentAuth::class` directly is caught too.
     */
    public function test_seller_reject_route_does_not_require_agent_auth(): void
    {
        $route = $this->sellerRejectRoute();

        $this->assertNotContains(
            'agentAuth',
            $route->gatherMiddleware(),
            'the Seller/listing owner rejects a Hire Agent bid, not the bidding agent'
        );

        $this->assertNotContains(
            AgentAuth::class,
            $this->resolvedMiddleware($route),
            'AgentAuth must not reach this route under any spelling'
        );
    }

    /**
     * Seller was the outlier; the other three roles are asserted so that "the Seller
     * one was fixed" cannot quietly become "a different role broke the same way".
     */
    public function test_no_hire_agent_reject_route_requires_agent_auth(): void
    {
        foreach (self::HIRE_AGENT_REJECT_ROUTE_NAMES as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "the {$name} route is missing entirely");

            $this->assertNotContains(
                'agentAuth',
                $route->gatherMiddleware(),
                "{$name}: rejecting a Hire Agent bid is a listing-owner action"
            );

            $this->assertNotContains(
                AgentAuth::class,
                $this->resolvedMiddleware($route),
                "{$name}: AgentAuth must not reach this route under any spelling"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Registration uniqueness
    // -------------------------------------------------------------------------

    /**
     * Not the mechanism of THIS defect — reject had a single registration — but the
     * accept defect proves a second one can be added silently, and it would replace
     * the consumer-scoped registration this branch just created.
     */
    public function test_seller_reject_route_is_registered_exactly_once(): void
    {
        $counts = $this->rawRegistrations();

        $this->assertSame(
            1,
            $counts['POST hire/agent/seller/bid/reject'] ?? 0,
            'a second registration of this method + URI silently replaces the first'
        );
    }

    /**
     * Guards the defect CLASS while leaving the already-known unrelated legacy
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
    // Accept must stay fixed
    // -------------------------------------------------------------------------

    /**
     * PR #143's fix and this one live a few lines apart in the same file. Asserting
     * accept here means a careless edit to the Seller hire-agent block cannot undo
     * it while this class still passes.
     */
    public function test_seller_accept_route_remains_free_of_agent_auth(): void
    {
        $route = Route::getRoutes()->getByName('acceptSABid');

        $this->assertNotNull($route, 'the acceptSABid route is missing entirely');
        $this->assertSame('hire/agent/seller/bid/accept', $route->uri());
        $this->assertSame(
            'App\Http\Controllers\SellerAgentAuctionController@acceptSABid',
            $route->getActionName()
        );
        $this->assertNotContains('agentAuth', $route->gatherMiddleware());
        $this->assertNotContains(AgentAuth::class, $this->resolvedMiddleware($route));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function sellerRejectRoute(): RoutingRoute
    {
        $route = Route::getRoutes()->getByName('rejectSABid');

        $this->assertNotNull($route, 'the rejectSABid route is missing entirely');

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
