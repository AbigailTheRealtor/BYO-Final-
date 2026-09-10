<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Master gate for BidYourOffer Explore.
 *
 * Mirrors CheckMatchCheckEnabled / CheckAgentAiV2Enabled: with the flag off
 * every Explore route 404s, including the viewport API. Fails closed — a config
 * that did not load reads as off.
 *
 * 404 rather than 403 on purpose. A disabled feature should be invisible, not
 * advertised as something that exists and is being withheld.
 *
 * This gate says nothing about VOW. That tier has its own, independent refusal
 * in {@see \App\Services\Explore\Vow\VowAvailability}, which is not a flag.
 */
class CheckExploreEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('explore.enabled', false)) {
            return $next($request);
        }

        abort(404);
    }
}
