<?php

namespace App\Http\Controllers\MatchCheck;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchCheck\MatchCheckLookupRequest;
use App\Services\Stellar\MatchCheck\MatchCheckAnalysis;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 *   whole-string match for now; robust, locality-preserving address resolution (C1) is a
 *   DELIBERATE POST-LAUNCH deferral (git-C14.2), not a pending pre-launch task — see the TODO
 *   on lookup() and docs/match-check-phase4-git-c14.2-address-resolution-provider-eval.md.
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
     *
     * git-C14.1 (Post/Redirect/Get): this POST does not render — it flashes the resulting
     * MatchCheckAnalysis and redirects to the GET result route, so a browser refresh or
     * back never re-POSTs (which would re-run the Bridge lookup and burn the throttle).
     */
    public function lookup(MatchCheckLookupRequest $request, MatchCheckOrchestrator $orchestrator): RedirectResponse
    {
        // Laravel 8 FormRequest::validated() returns the full validated array (no key arg).
        $data = $request->validated();
        $user = $request->user();

        // TODO (git-C14.2 — DEFERRED POST-LAUNCH): free-text address lookup passes the whole string
        // to the orchestrator's forgiving substring match. That is SAFE — an address that does not
        // match a stored record yields NOT_FOUND — but it misses real listings whose stored address
        // differs in its city/state/ZIP tail (the original C1 gap). Proper address resolution
        // (parsing/geocoding with a LOCALITY-PRESERVING lookup, so a street match can never silently
        // score a wrong-city listing) needs its own slice. The heuristic street-term parser explored
        // for C1 was reverted here: dropping city/state risked a confident wrong-city SCORED result,
        // a worse failure mode than the baseline NOT_FOUND.
        //
        // DECISION (2026-07-08, owner-signed): this is a deliberate post-launch enhancement, not a
        // pending pre-launch task. For launch we add NO Google Geocoding here, use NO public
        // Nominatim in production, and keep this safe NOT_FOUND baseline. Provider evaluation and
        // future options (paid OSM-backed geocoder e.g. LocationIQ/Geoapify, libpostal, or a full
        // Nominatim/Photon/Leaflet stack) are recorded in
        // docs/match-check-phase4-git-c14.2-address-resolution-provider-eval.md.
        $analysis = match ($data['mode']) {
            'mls'         => $orchestrator->analyzeByMlsNumber($data['mls_number'], $user),
            'listing_key' => $orchestrator->analyzeByListingKey($data['listing_key'], $user),
            default       => $orchestrator->analyzeByAddress(['address' => $data['address']], $user),
        };

        // PRG: carry the analysis across a redirect (session flash) instead of rendering here.
        return redirect()
            ->route('match-check.result')
            ->with('matchCheckAnalysis', $analysis);
    }

    /**
     * Render the result of the most recent lookup (git-C14.1 · Post/Redirect/Get).
     *
     * The MatchCheckAnalysis is carried across the redirect in the session flash bag. A
     * direct hit or a refresh — where the one-shot flash has already been consumed — has
     * nothing to render, so it redirects back to the lookup form rather than erroring or
     * silently re-running a lookup. Rendering is otherwise identical to the pre-PRG path:
     * the same MatchCheckAnalysis flows through the same result view.
     */
    public function result(Request $request): View|RedirectResponse
    {
        $analysis = $request->session()->get('matchCheckAnalysis');

        if (! $analysis instanceof MatchCheckAnalysis) {
            return redirect()->route('match-check.show');
        }

        return view('match-check.result', ['analysis' => $analysis]);
    }
}
