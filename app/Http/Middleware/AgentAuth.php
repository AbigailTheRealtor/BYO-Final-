<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Agent-only routes. Authentication is enforced upstream by the `auth`
        // middleware; this gate restricts access to the agent persona only.
        // Canonical agent type is 'agent' (legacy 'seller_agent'/'buyer_agent'
        // accounts were already excluded by the prior logic and remain excluded).
        // Previously this only redirected two legacy agent types and let every
        // other account (incl. consumers) through — an inverted check.
        $user = Auth::user();
        if (!$user || $user->user_type !== 'agent') {
            return redirect()->to(route('dashboard'));
        }
        return $next($request);
    }
}
