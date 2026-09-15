<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('ask-ai-api', function (Request $request) {
            $limit = config('ask_ai.rate_limit_per_minute', 20);
            return Limit::perMinute($limit)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function ($request, $headers) {
                    return response()->json([
                        'success' => false,
                        'status'  => 'failed',
                        'error'   => 'Too many requests. Please slow down and try again.',
                    ], 429, $headers);
                });
        });

        /*
         | Location DNA free-text address lookup.
         |
         | TWO CEILINGS, MEASURING TWO DIFFERENT THINGS. The per-minute limit is
         | about one person and one form: a Radius Search or an Important Place
         | is typed and submitted deliberately, so twenty in a minute is already
         | far more than the interaction produces and anything past it is a stuck
         | key, a script, or a bug. The hourly limit is about the provider: the US
         | Census geocoder is free, publishes no rate limit and bills nobody, so
         | the only signal that we are abusing it is the day it stops answering.
         |
         | This is NOT the provider's ceiling. `CENSUS_GEOCODER_HOURLY_CAP` and
         | `_DAILY_CAP` are, and they are application-wide and apply to every
         | caller including this one. These two are per-identity and exist so one
         | account cannot spend the application's whole budget on its own.
         |
         | Keyed by user id where there is one and by IP where there is not —
         | the route requires authentication, so the IP branch is the fallback
         | for a session that expired mid-form rather than the normal path.
         */
        RateLimiter::for('address-lookup', function (Request $request) {
            $by = optional($request->user())->id ?: $request->ip();

            return [
                Limit::perMinute(20)->by('ldna-lookup-min:' . $by),
                Limit::perHour(200)->by('ldna-lookup-hr:' . $by),
            ];
        });
    }
}
