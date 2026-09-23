<?php

namespace App\Support\AskAi;

use App\Http\Controllers\BuyerOfferListingController;
use App\Http\Controllers\LandlordOfferListingController;
use App\Http\Controllers\SellerOfferListingController;
use App\Http\Controllers\TenantOfferListingController;
use App\Services\AskAi\AskAiViewerAuthorizationService;

/**
 * May a NON-OWNER ask Ask AI about this listing at all?
 *
 * Exactly when that listing's public Offer Listing page would render for them. The page
 * decides, so this class asks the page's own resolver rather than restating its rules —
 * a second copy of "is this an Offer Listing" would drift from the first, and the drift
 * would be a draft, a Hire listing or an archived listing answering questions to strangers.
 *
 * What a non-owner may then LEARN is not decided here. The runner is handed the public
 * scope and every fact is filtered by the existing per-fact visibility policy
 * (AskAiViewerAuthorizationService / SnapshotFactVisibility / the public question card).
 * This gate is the listing-level half only: it never widens a tier.
 *
 * Fails closed: an unknown listing type, a missing record, any other workflow, and any
 * error while deciding are all "not publicly viewable".
 */
final class AskAiPublicListingAccess
{
    /** canonical role => the public page controller whose resolver decides. */
    private const PAGE_CONTROLLERS = [
        'seller'   => SellerOfferListingController::class,
        'landlord' => LandlordOfferListingController::class,
        'buyer'    => BuyerOfferListingController::class,
        'tenant'   => TenantOfferListingController::class,
    ];

    public static function isPubliclyViewable(string $listingType, int $listingId): bool
    {
        $role       = AskAiViewerAuthorizationService::canonicalRole($listingType);
        $controller = self::PAGE_CONTROLLERS[$role] ?? null;
        if ($controller === null || $listingId <= 0) {
            return false;
        }

        try {
            $resolved = app($controller)->resolveOfferListing($listingId);
        } catch (\Throwable) {
            // abort(404) / findOrFail() for a missing record or another workflow, and any
            // other failure: none of them is permission.
            return false;
        }

        // The Tenant resolver returns [auction, meta]; the other three return the model.
        $auction = is_array($resolved) ? ($resolved[0] ?? $resolved['auction'] ?? null) : $resolved;
        if (! is_object($auction)) {
            return false;
        }

        // The same two rules every public page applies to a non-owner (WF-2, WF-4).
        if (filter_var($auction->is_archived ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return filter_var($auction->is_approved ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! filter_var($auction->is_draft ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
