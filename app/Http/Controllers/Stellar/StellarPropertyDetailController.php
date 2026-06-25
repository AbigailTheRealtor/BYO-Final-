<?php

namespace App\Http\Controllers\Stellar;

use App\Http\Controllers\Controller;
use App\Models\BridgeProperty;
use App\Services\Stellar\PropertyDetailViewMapper;
use Illuminate\Http\Request;

/**
 * Shared property detail controller — role-agnostic.
 *
 * Serves Buyer, Tenant, Agent preview, Landlord preview, and Ask AI flows.
 * Role-specific context (match score, bid context) is NOT handled here;
 * callers may pass it via query params and the view layer may render it
 * optionally via the $context variable.
 */
class StellarPropertyDetailController extends Controller
{
    public function __construct(
        private PropertyDetailViewMapper $mapper
    ) {}

    public function show(Request $request, string $listingKey): \Illuminate\View\View
    {
        $listing = BridgeProperty::where('listing_key', $listingKey)
            ->where('standard_status', 'Active')
            ->firstOrFail();

        // IDX gate — same normalisation logic as BuyerMatchService
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

        $property = $this->mapper->map($listing);

        // Optional back-link context supplied by the results page
        $criteriaId   = $request->input('criteria_id');
        $criteriaType = $request->input('criteria_type', 'buyer');

        $backUrl = $criteriaId
            ? route('stellar.buyer.results', array_filter([
                'criteria_id'   => $criteriaId,
                'criteria_type' => $criteriaType !== 'buyer' ? $criteriaType : null,
              ]))
            : route('stellar.buyer.results');

        return view('stellar.property.detail', [
            'property' => $property,
            'backUrl'  => $backUrl,
        ]);
    }
}
