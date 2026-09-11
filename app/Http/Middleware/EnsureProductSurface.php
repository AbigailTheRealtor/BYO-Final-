<?php

namespace App\Http\Middleware;

use App\Support\Product\ProductContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Server-side product boundary. Hiding a link is not isolation.
 *
 * Runs for every web request, after routing, so it sees the route that WOULD
 * have been served. In BidYourAgent mode a route the catalog identifies as a
 * BidYourOffer-only surface is refused outright — typing the URL, replaying a
 * bookmark, or POSTing to it directly all land on the same answer as the
 * navigation that no longer offers it.
 *
 * 404, not 403 or a redirect. 404 is what this repository already uses for a
 * feature that should not exist for the caller (CheckMatchCheckEnabled,
 * CheckAgentAiV2Enabled), and it is the only honest answer: on a BidYourAgent
 * deployment that surface does not exist. A 403 would confirm it does, and a
 * redirect to somewhere friendlier would map the hidden product for anyone
 * willing to read a Location header.
 *
 * THIS MIDDLEWARE NEVER GRANTS ANYTHING. It can only refuse. Every auth,
 * verified, owner and agentAuth check on a route still runs exactly as before —
 * product visibility and authorization are separate layers and both are enforced.
 * In `combined` mode it is a no-op.
 */
class EnsureProductSurface
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (ProductContext::servesBidYourOffer()) {
            return $next($request);
        }

        $route = $request->route();

        if ($route === null) {
            return $next($request);
        }

        if (! ProductContext::allowsRoute($request->method(), $route->uri())) {
            abort(404);
        }

        return $next($request);
    }
}
