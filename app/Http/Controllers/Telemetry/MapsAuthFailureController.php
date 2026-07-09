<?php

namespace App\Http\Controllers\Telemetry;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receives the browser's report that Google Maps JS rejected the API key
 * (Phase 0 / S3b).
 *
 * WHY THIS EXISTS
 * ---------------
 * Server-side telemetry (GoogleOutboundTelemetryMiddleware) observes only the calls
 * our server makes: 12 Livewire Autocomplete proxies and 6 geocoding call sites.
 * The 49 browser `google.maps.places.Autocomplete` instantiations and 8 Maps JS
 * loaders talk to Google *directly from the user's browser*. No server metric can
 * ever see them.
 *
 * Google Maps JS invokes `window.gm_authFailure()` when a key is invalid, revoked,
 * expired, or unauthorised for the requesting referrer. Reporting that callback here
 * tells us the browser key's true state with certainty, from our own logs, without
 * issuing a single billed request (SIA-D32: telemetry, never a probe).
 *
 * Together the two signals give a complete answer to Q10.
 */
class MapsAuthFailureController extends Controller
{
    /** Cache key mirroring the server-side counter, for the browser key. */
    public const COUNTER_KEY = 'telemetry:google_maps_auth_failures_total';

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! Cache::has(self::COUNTER_KEY)) {
            Cache::forever(self::COUNTER_KEY, 0);
        }

        Cache::increment(self::COUNTER_KEY);

        Log::error('google_maps_auth_failure', [
            'page'       => $validated['page'] ?? $request->headers->get('referer'),
            'user_agent' => $request->userAgent(),
            'user_id'    => $request->user()?->id,
        ]);

        return response()->json(['recorded' => true]);
    }
}
