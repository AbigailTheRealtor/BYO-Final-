<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * EnsureOfferPlayoffAccess
 *
 * Route-level guard for all agent-facing Offer Playoff routes.
 * Uses the 'offer-playoff' Gate as the single source of truth —
 * no role or ID checks here.
 */
class EnsureOfferPlayoffAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || Gate::denies('offer-playoff')) {
            abort(403, 'You do not have access to Offer Playoff.');
        }

        return $next($request);
    }
}
