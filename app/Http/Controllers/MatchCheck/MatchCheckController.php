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
 *   lookup, or enrichment happens here. v1 exposes MLS # + address modes only (owner
 *   §7.2); the Buyer/Tenant intent is auto-selected inside the orchestrator, so there is
 *   deliberately no Seller/Landlord variant of this surface (owner §7.5).
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
     */
    public function lookup(MatchCheckLookupRequest $request, MatchCheckOrchestrator $orchestrator): View
    {
        // Laravel 8 FormRequest::validated() returns the full validated array (no key arg).
        $data = $request->validated();
        $user = $request->user();

        $analysis = $data['mode'] === 'mls'
            ? $orchestrator->analyzeByMlsNumber($data['mls_number'], $user)
            : $orchestrator->analyzeByAddress(['address' => $data['address']], $user);

        return view('match-check.result', [
            'analysis' => $analysis,
            'mode'     => $data['mode'],
        ]);
    }
}
