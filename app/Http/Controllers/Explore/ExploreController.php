<?php

namespace App\Http\Controllers\Explore;

use App\Http\Controllers\Controller;
use App\Services\Explore\ExploreAccessTier;
use App\Services\Explore\ExploreGoogleConfig;
use App\Services\Explore\ExploreTransactionType;
use App\Services\Explore\Vow\VowAvailability;
use Illuminate\Http\Request;

/**
 * The Explore shell.
 *
 * PUBLIC ROUTE, BUT THE ROUTE IS NOT THE PERMISSION
 * -------------------------------------------------
 * `/explore` is reachable without authentication, because the listings it
 * discovers are already published on public pages. That does NOT make anything
 * visible: this action renders a page and no listing data whatsoever. Every
 * property fact is fetched from {@see ExploreListingApiController}, which
 * enforces its own eligibility on every request. A public shell over an
 * authorising endpoint is the arrangement; a public shell that pre-loads data
 * would move the decision into a template, which is where it must never be.
 *
 * THE PROPERTY INTELLIGENCE CONTROL IS ABSENT, NOT DISABLED
 * ---------------------------------------------------------
 * A greyed-out "Property Intelligence" button tells every visitor that
 * BidYourOffer has off-market MLS intelligence it is choosing not to give them.
 * It does not have it. The control is not rendered at all.
 */
class ExploreController extends Controller
{
    public function __construct(
        private readonly ExploreGoogleConfig $google,
        private readonly VowAvailability $vow,
    ) {}

    public function index(Request $request)
    {
        $tier = $this->vow->decideTier($request->user());

        // The credential is emitted ONLY when the renderer will actually run.
        //
        // `browserKey()` answers "is one configured", which is a different
        // question from "may Google be loaded" — the kill switch can be closed
        // over a perfectly valid key. Printing it anyway would put a live
        // billable credential into the HTML of a page that is deliberately not
        // using it, where anyone can lift it. A switched-off provider should
        // leave no trace on the page at all.
        $googleReady = $this->google->isReady();

        return view('explore.index', [
            'googleReady'        => $googleReady,
            'googleUnavailable'  => $this->google->unavailableReason(),
            'googleKey'          => $googleReady ? $this->google->browserKey() : null,
            'googleMapId'        => $googleReady ? $this->google->mapId() : null,
            'googleApiVersion'   => $this->google->apiVersion(),
            'googleLibraries'    => $this->google->libraries(),
            'defaultCamera'      => (array) config('explore.default_camera', []),
            'accessTier'         => $tier->value,
            // False today and structurally so. See VowAvailability.
            'propertyIntelligenceAvailable' => $tier === ExploreAccessTier::VOW_REGISTERED,
            'filters'            => [
                ['value' => '',                                 'label' => 'All Listings'],
                ['value' => ExploreTransactionType::SALE->value, 'label' => ExploreTransactionType::SALE->consumerLabel()],
                ['value' => ExploreTransactionType::RENT->value, 'label' => ExploreTransactionType::RENT->consumerLabel()],
            ],
            'maxResults'         => (int) config('explore.viewport.max_results', 150),
            'maxSpanDegrees'     => (float) config('explore.viewport.max_span_degrees', 1.0),
        ]);
    }
}
