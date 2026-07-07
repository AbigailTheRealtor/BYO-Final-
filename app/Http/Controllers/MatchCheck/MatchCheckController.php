<?php

namespace App\Http\Controllers\MatchCheck;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchCheck\MatchCheckLookupRequest;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use Illuminate\View\View;

/**
 * MatchCheckController — the first consumer-facing surface for the Buyer/Tenant Match
 * Check feature (MLS Direct Import · Phase 4 · git-C14).
 *
 * GATING:
 *   Both routes live behind the ['auth', 'match-check'] middleware group. The
 *   `match-check` alias (CheckMatchCheckEnabled) 404s every route while
 *   config('mls_match_check.enabled') is false — its default. So with the flag OFF this
 *   controller is never reached. Defense-in-depth: MatchCheckOrchestrator's analyzeBy*()
 *   also self-gate to MatchCheckAnalysis::disabled() before any lookup, DB read, or
 *   enrichment dispatch, so no external I/O occurs even if a route were somehow reached.
 *
 * SCOPE (git-C14):
 *   Thin surface ONLY. This controller holds no business logic — it validates the
 *   identifier and delegates 100% to MatchCheckOrchestrator, then hands the returned
 *   MatchCheckAnalysis (a data-only, F7-safe value object) to a Blade view. No scoring,
 *   lookup, or enrichment happens here. v1 exposes MLS #, address, and (for AMBIGUOUS
 *   disambiguation re-submit) listing_key modes; the Buyer/Tenant intent is auto-selected
 *   inside the orchestrator, so there is deliberately no Seller/Landlord variant of this
 *   surface (owner §7.5). Free-text address lookup uses the orchestrator's baseline
 *   whole-string match for now; robust, locality-preserving address resolution (C1) is
 *   deferred to its own slice (git-C14.2) — see the TODO on lookup().
 */
class MatchCheckController extends Controller
{
    /**
     * Render the empty lookup form.
     */
    public function show(): View
    {
        return view('match-check.show');
    }

    /**
     * Resolve a consumer identifier to a MatchCheckAnalysis and render its state. Every
     * branch of the analysis is rendered by the result view (SCORED / AMBIGUOUS /
     * NOT_FOUND / BLOCKED / NO_CRITERIA / CRITERIA_NOT_LOADED). DISABLED is unreachable
     * here — the middleware 404s before the controller runs.
     *
     * The `listing_key` mode is not a user-typed field; it backs the AMBIGUOUS
     * disambiguation re-submit (git-C14 · C2), resolving the chosen candidate by its
     * globally-unique RESO ListingKey for an exact, no-re-ambiguity result.
     */
    public function lookup(MatchCheckLookupRequest $request, MatchCheckOrchestrator $orchestrator): View
    {
        // Laravel 8 FormRequest::validated() returns the full validated array (no key arg).
        $data = $request->validated();
        $user = $request->user();

        // TODO (git-C14.2 — DEFERRED): free-text address lookup passes the whole string to the
        // orchestrator's forgiving substring match. That is SAFE — an address that does not match a
        // stored record yields NOT_FOUND — but it misses real listings whose stored address differs
        // in its city/state/ZIP tail (the original C1 gap). Proper address resolution
        // (parsing/geocoding with a LOCALITY-PRESERVING lookup, so a street match can never silently
        // score a wrong-city listing) needs its own slice. The heuristic street-term parser explored
        // for C1 was reverted here: dropping city/state risked a confident wrong-city SCORED result,
        // a worse failure mode than the baseline NOT_FOUND.
        $analysis = match ($data['mode']) {
            'mls'         => $orchestrator->analyzeByMlsNumber($data['mls_number'], $user),
            'listing_key' => $orchestrator->analyzeByListingKey($data['listing_key'], $user),
            default       => $orchestrator->analyzeByAddress(['address' => $data['address']], $user),
        };

        return view('match-check.result', ['analysis' => $analysis]);
    }
}
