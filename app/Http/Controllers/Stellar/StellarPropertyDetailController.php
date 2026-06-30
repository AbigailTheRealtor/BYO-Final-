<?php

namespace App\Http\Controllers\Stellar;

use App\Http\Controllers\Controller;
use App\Models\BridgeProperty;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\PropertyPersonalityService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\Stellar\PropertyDetailViewMapper;
use App\Services\Stellar\PropertyMatchContextService;
use Illuminate\Http\Request;

/**
 * Canonical property detail page — role-agnostic.
 *
 * Section 1 (MLS data) is always rendered from bridge_properties.raw_json.
 * Section 2 (Matchmaker Analysis) is rendered from optional enrichment services;
 * each sub-section degrades gracefully when data is unavailable.
 */
class StellarPropertyDetailController extends Controller
{
    public function __construct(
        private PropertyDetailViewMapper    $mapper,
        private LocationDnaSummaryService   $locationSummaryService,
        private PropertyPersonalityService  $personalityService,
        private PropertyMatchContextService $matchContextService,
    ) {}

    public function show(Request $request, string $listingKey): \Illuminate\View\View
    {
        $listing = BridgeProperty::where('listing_key', $listingKey)
            ->where('standard_status', 'Active')
            ->firstOrFail();

        // IDX gate — same normalisation as BuyerMatchService
        $raw = $listing->raw_json ? (json_decode($listing->raw_json, true) ?? []) : [];
        if (array_key_exists('IDXParticipationYN', $raw)) {
            $idxRaw    = $raw['IDXParticipationYN'];
            $idxPassed = is_bool($idxRaw)
                ? $idxRaw
                : filter_var($idxRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
            if (!$idxPassed) {
                abort(403, 'This listing is not eligible for IDX display.');
            }
        }

        // Section 1 — MLS data
        $property = $this->mapper->map($listing);

        // Back-link context from results page
        $criteriaId   = $request->input('criteria_id');
        $criteriaType = $request->input('criteria_type', 'buyer');

        // WF-1 normalization: the Ask AI widget posts to the owner-only
        // listing-question endpoint, so only expose it when the viewer actually
        // OWNS this (criteria_type, criteria_id). A tampered/foreign criteria_id
        // then renders the "available from your saved criteria" notice instead of
        // a control that would 403. Does not weaken the endpoint's own check.
        $askAiCriteriaId = null;
        if ($criteriaId && $request->user()) {
            $criteriaTable = $criteriaType === 'tenant'
                ? 'tenant_agent_auctions'
                : 'buyer_agent_auctions';
            $ownsCriteria = \Illuminate\Support\Facades\DB::table($criteriaTable)
                ->where('id', (int) $criteriaId)
                ->where('user_id', $request->user()->id)
                ->exists();
            if ($ownsCriteria) {
                $askAiCriteriaId = $criteriaId;
            }
        }

        $backUrl = $criteriaId
            ? route('stellar.buyer.results', array_filter([
                'criteria_id'   => $criteriaId,
                'criteria_type' => $criteriaType !== 'buyer' ? $criteriaType : null,
              ]))
            : route('stellar.buyer.results');

        // Section 2a — Match context (requires criteria_id in URL and logged-in user)
        $matchContext = null;
        if ($criteriaId && $request->user()) {
            try {
                $matchContext = $this->matchContextService->resolve(
                    $listing,
                    $criteriaType,
                    (int) $criteriaId,
                    $request->user()
                );
            } catch (\Throwable) {
                // Non-fatal — detail page renders without match score
            }
        }

        // Section 2b — Location DNA summary (pre-computed by pipeline)
        $locationSummary = [];
        try {
            $locationSummary = $this->locationSummaryService->summarizeForListing('bridge', $listing->id);
        } catch (\Throwable) {
        }

        // Section 2c — Property personality + target audience (requires DNA profile)
        $personality = null;
        try {
            $dnaProfile = PropertyDnaProfile::where('listing_type', 'bridge')
                ->where('listing_id', $listing->id)
                ->first();

            if ($dnaProfile) {
                $result = $this->personalityService->generate($dnaProfile, $locationSummary);
                if ($result['success'] ?? false) {
                    $personality = array_merge($result, [
                        'archetype_tags'  => $dnaProfile->ai_buyer_archetype_tags ?? [],
                        'marketing_hooks' => $dnaProfile->ai_marketing_hooks       ?? [],
                        'walk_score'      => $dnaProfile->walk_score    ?? null,
                        'transit_score'   => $dnaProfile->transit_score ?? null,
                        'bike_score'      => $dnaProfile->bike_score    ?? null,
                    ]);
                }
            }
        } catch (\Throwable) {
        }

        return view('stellar.property.detail', [
            'property'        => $property,
            'backUrl'         => $backUrl,
            'criteriaId'      => $criteriaId,
            'criteriaType'    => $criteriaType,
            'askAiCriteriaId' => $askAiCriteriaId,
            'matchContext'    => $matchContext,
            'locationSummary' => $locationSummary,
            'personality'     => $personality,
            'listingId'       => $listing->id,
        ]);
    }
}
