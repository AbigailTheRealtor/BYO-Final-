<?php

namespace App\Http\Middleware;

use App\Support\VirtualDrive\VirtualDriveProofGate;
use Closure;
use Illuminate\Http\Request;

/**
 * 404s every Virtual Drive proof route unless VirtualDriveProofGate allows it.
 *
 * 404, not 403: an internal proof that answers "forbidden" in production tells
 * the caller it exists.
 */
class CheckVirtualDriveProofEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! VirtualDriveProofGate::allows()) {
            abort(404);
        }

        return $next($request);
    }
}
