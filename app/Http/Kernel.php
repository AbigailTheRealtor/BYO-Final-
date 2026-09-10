<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Product boundary. Last in the group so it runs after routing and
            // sees the matched route; a no-op unless this deployment serves a
            // single product. It can only refuse — never grant.
            \App\Http\Middleware\EnsureProductSurface::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Middleware execution order, where it must not be left to chance.
     *
     * This is the framework's own default list with ONE addition:
     * EnsureProductSurface, placed after the session is available and BEFORE
     * authentication.
     *
     * WHY THE POSITION MATTERS. Laravel sorts a route's gathered middleware by this
     * list, and `Authenticate` implements AuthenticatesRequests, so without an entry
     * here the product gate ran AFTER auth: a signed-out visitor asking a
     * BidYourAgent deployment for an Offer Listing URL was redirected to log in —
     * confirming the surface exists, and handing them a 404 only after they had
     * signed in. It must answer the same way to everyone: this surface is not here.
     *
     * Nothing else in this list is reordered. The gate needs the session no more
     * than it needs auth, but it sits after StartSession so that the 404 it raises
     * is rendered by the ordinary error page rather than in a half-booted request.
     *
     * @var array<int, class-string>
     */
    protected $middlewarePriority = [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\EnsureProductSurface::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'adminAuth' => \App\Http\Middleware\AdminAuth::class,
        'sellerAuth' => \App\Http\Middleware\SellerAuth::class,
        'buyerAuth' => \App\Http\Middleware\BuyerAuth::class,
        'sellerAgentAuth' => \App\Http\Middleware\SellerAgentAuth::class,
        'buyerAgentAuth' => \App\Http\Middleware\BuyerAgentAuth::class,
        'buyerBidderAuth' => \App\Http\Middleware\BuyerBidderAuth::class,
        'sellerBidderAuth' => \App\Http\Middleware\SellerBidderAuth::class,
        'tenantBidderAuth' => \App\Http\Middleware\TenantBidderAuth::class,
        'landlordBidderAuth' => \App\Http\Middleware\LandlordBidderAuth::class,
        'noAdmin' => \App\Http\Middleware\NoAdminAuth::class,
        'agentAuth' => \App\Http\Middleware\AgentAuth::class,
        'ensureAgent'          => \App\Http\Middleware\EnsureAgent::class,
        'offerPlayoffAccess'   => \App\Http\Middleware\EnsureOfferPlayoffAccess::class,
        'bya.beta.access'      => \App\Http\Middleware\ByaBetaAccessMiddleware::class,
        'bya.consumer.beta.access' => \App\Http\Middleware\ByaConsumerBetaAccessMiddleware::class,
        'landlordAuth' => \App\Http\Middleware\LandlordAuth::class,
        'tenantAuth' => \App\Http\Middleware\TenantAuth::class,
        'agent-ai-v2' => \App\Http\Middleware\CheckAgentAiV2Enabled::class,
        'match-check' => \App\Http\Middleware\CheckMatchCheckEnabled::class,
    ];
}
