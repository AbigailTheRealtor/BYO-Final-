<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate for the consumer-facing Buyer/Tenant Match Check feature (Phase 4).
 *
 * Mirrors CheckAgentAiV2Enabled: when the master flag is OFF, all /match-check
 * routes 404 (feature is invisible). Default OFF via config/mls_match_check.php.
 */
class CheckMatchCheckEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('mls_match_check.enabled', false)) {
            return $next($request);
        }

        abort(404);
    }
}
