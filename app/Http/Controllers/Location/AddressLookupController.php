<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Services\Location\Lookup\AddressLookupQuery;
use App\Services\Location\Lookup\AddressLookupResult;
use App\Services\Location\Lookup\AddressLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one HTTP entry point for Location DNA free-text address lookup.
 *
 * WHY AN ENDPOINT AND NOT A LIVEWIRE METHOD
 * -----------------------------------------
 * The Search Areas widget has eight host surfaces, and two of them —
 * `buyer_criteria` and `tenant_criteria`, add and edit — are legacy
 * controller-rendered Blade forms with no Livewire component behind them. A
 * Livewire action would serve six of the eight and leave the other two with the
 * behaviour this work exists to remove. One endpoint serves all eight
 * identically.
 *
 * WHY IT IS AUTHENTICATED
 * -----------------------
 * Not because an address is a secret — it is not — but because this is the
 * public face of a shared, free, rate-limited provider whose budget the whole
 * application draws on. An unauthenticated lookup box is a geocoding proxy the
 * internet can point a script at, and the first thing that happens when it is
 * found is that our ceiling is spent by somebody else. The surfaces that use it
 * are all behind a login already.
 *
 * WHAT CROSSES THE WIRE
 * ---------------------
 * {@see AddressLookupResult::toResponse()} decides, and it is the only thing
 * that decides. On success: `ok`, `address`, `lat`, `lng`, `precision`. On
 * failure: `ok` and one sentence. There is no provider name, no provider URL,
 * no upstream status code, no exception message and no credential — and not
 * because they are stripped here, but because the service never carries them
 * this far.
 *
 * ALWAYS HTTP 200 FOR AN ANSWERED LOOKUP
 * --------------------------------------
 * Including "not located". A 4xx or 5xx would make an ordinary miss
 * indistinguishable from a broken endpoint to every network panel, retry policy
 * and error reporter between here and the user, and would tempt a client into
 * retrying something that will never succeed. The failure is in the body, where
 * the caller reads it. Validation failure and rate limiting still use their own
 * status codes, because those are not answers.
 */
class AddressLookupController extends Controller
{
    /**
     * Locate a typed address.
     *
     * POST, deliberately: it is not idempotent from the provider's point of
     * view (it can spend budget), it must not be cached by an intermediary,
     * and a GET would put user-typed addresses in server logs and browser
     * history. POST inside the `web` group also means Laravel's CSRF middleware
     * applies, which is the property that stops another site driving this
     * endpoint with a logged-in user's cookies.
     */
    public function __invoke(Request $request, AddressLookupService $lookup): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:' . AddressLookupQuery::MAX_INPUT_LENGTH],
        ]);

        $result = $lookup->lookup((string) $validated['address']);

        return response()->json($result->toResponse());
    }
}
