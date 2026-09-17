<?php

namespace App\Http\Middleware;

use App\Support\ListingPreferences\ListingPreferenceAvailability;
use Closure;
use Illuminate\Http\Request;

/**
 * 404s every listing-preference route while the feature is off.
 *
 * Mirrors `explore` and `CheckMatchCheckEnabled`: a disabled feature is
 * INVISIBLE rather than advertised, so an off deployment does not publish the
 * shape of an endpoint that refuses.
 *
 * Fail-closed — the gate is `=== true`, so a config that did not load reads as
 * off. The flag is read only through ListingPreferenceAvailability, which is
 * also what the renderer asks, so the control and the endpoint cannot disagree.
 */
class EnsureListingPreferencesEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! ListingPreferenceAvailability::featureEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
