<?php

namespace App\Http\Middleware;

use App\Models\ByaBetaAccessLog;
use App\Models\ListingCompatibilityScore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * ByaBetaAccessMiddleware
 *
 * Route-level guard for the hidden BYA compatibility beta route.
 * Uses the 'bya-beta-access' Gate (with the requested score model) as the
 * single source of truth — all policy logic lives in the Gate definition.
 *
 * This middleware's only responsibilities are:
 *   1. Resolve the ListingCompatibilityScore from the route parameter.
 *   2. Call Gate::inspect('bya-beta-access', $score) to get the access decision.
 *   3. Write one ByaBetaAccessLog row for every attempt (allowed or denied).
 *   4. Abort 403 if the Gate denied; pass through otherwise.
 *
 * This middleware is always preceded by the `auth` middleware on the route,
 * so Auth::id() is guaranteed to be non-null when handle() executes.
 * Log rows for unauthenticated attempts (which never reach this middleware)
 * are intentionally out of scope — those are blocked at the auth layer.
 */
class ByaBetaAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $id     = (int) $request->route('id');
        $userId = Auth::id();

        // Defensive guard: auth middleware should always run first, but we
        // never write a log with user_id = 0 (which would violate the FK).
        if (!$userId) {
            abort(403, 'Access denied.');
        }

        $score = ListingCompatibilityScore::find($id);

        // If the score does not exist we cannot satisfy the FK constraint on
        // listing_compatibility_score_id, so we skip logging and return 403.
        // The controller's findOrFail() would surface a 404 in the normal flow.
        if (!$score) {
            abort(403, 'Access denied.');
        }

        $gateResponse = Gate::inspect('bya-beta-access', $score);

        ByaBetaAccessLog::create([
            'user_id'                        => $userId,
            'listing_compatibility_score_id' => $score->id,
            'allowed'                        => $gateResponse->allowed(),
            'denial_reason'                  => $gateResponse->allowed() ? null : $gateResponse->message(),
        ]);

        if (!$gateResponse->allowed()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
