<?php

namespace App\Http\Middleware;

use App\Support\ListingPreferences\Taste\TasteDnaAvailability;
use Closure;
use Illuminate\Http\Request;

/**
 * 404s "Your Home Taste" unless BOTH the listing-preference master gate and the
 * Taste DNA gate are on.
 *
 * The same posture as EnsureListingPreferencesEnabled: an off feature is
 * invisible rather than advertised. Read only through TasteDnaAvailability,
 * which is also what the management page's tab asks, so the link and the page
 * cannot disagree.
 */
class EnsureTasteDnaEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! TasteDnaAvailability::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
