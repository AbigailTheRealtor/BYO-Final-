<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAgentAiV2Enabled
{
    /**
     * Keys of the per-scope flags in config/ask_ai.php.
     * Any one being true allows V2 routes to be accessible
     * so individual scopes can be phased in without the global flag.
     */
    private const PER_SCOPE_KEYS = [
        'agent_ai_v2_seller_enabled',
        'agent_ai_v2_landlord_enabled',
        'agent_ai_v2_buyer_enabled',
        'agent_ai_v2_tenant_enabled',
        'agent_ai_v2_agent_profile_enabled',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // Global kill switch takes precedence — if true, all V2 routes are open.
        if (config('ask_ai.agent_ai_v2_enabled', false)) {
            return $next($request);
        }

        // Per-scope phased rollout: if any individual scope is enabled,
        // allow the request through. The controller enforces the per-scope
        // check for the specific scope being requested.
        foreach (self::PER_SCOPE_KEYS as $key) {
            if (config("ask_ai.{$key}", false)) {
                return $next($request);
            }
        }

        abort(404);
    }
}
