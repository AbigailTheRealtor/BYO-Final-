<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAgentAiV2Enabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!config('ask_ai.agent_ai_v2_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
