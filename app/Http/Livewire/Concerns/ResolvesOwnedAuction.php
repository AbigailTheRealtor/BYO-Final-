<?php

namespace App\Http\Livewire\Concerns;

use App\Models\AcceptedBidSummary;
use Illuminate\Support\Facades\Auth;

/**
 * Centralised object-level authorization for the four Offer Listing edit
 * components (Seller / Buyer / Landlord / Tenant).
 *
 * Prior to this trait, the edit components resolved the auction being written
 * to from the client-controlled {auctionId} route param / Livewire property
 * with an unscoped Model::find(), allowing any authenticated user to overwrite
 * or read another user's listing (IDOR). This trait enforces the same
 * ownership model the components already use elsewhere:
 *
 *   - the listing owner (auction.user_id === Auth::id()) may always manage it;
 *   - for Seller/Landlord listings, an agent who has an AcceptedBidSummary
 *     assigned to that listing may also access it (this mirrors the existing
 *     $isOwner || $isAssigned rule in those components' render() methods, which
 *     governs the Location DNA panel). Buyer/Tenant pass a null
 *     $assignedListingType, making them owner-only.
 *
 * Guards are applied in BOTH mount() (initial GET) and hydrate() (every
 * subsequent Livewire action request) so that no write action method is
 * reachable for an unauthorized user and a tampered auctionId in a hydration
 * payload fails closed.
 */
trait ResolvesOwnedAuction
{
    /**
     * @param  class-string  $modelClass            The auction model class.
     * @param  mixed         $id                     The auction id (null = new record / create flow).
     * @param  string|null   $assignedListingType    AcceptedBidSummary.listing_type for the assigned-agent
     *                                                allowance, or null for owner-only roles.
     */
    protected function userCanManageAuction(string $modelClass, $id, ?string $assignedListingType = null): bool
    {
        // No id yet (brand-new create flow) — nothing to authorize against.
        if (empty($id)) {
            return true;
        }

        $userId = Auth::id();
        if (! $userId) {
            return false;
        }

        $isOwner = $modelClass::where('id', $id)
            ->where('user_id', $userId)
            ->exists();

        if ($isOwner) {
            return true;
        }

        if ($assignedListingType !== null) {
            return AcceptedBidSummary::where('listing_type', $assignedListingType)
                ->where('listing_id', $id)
                ->where('agent_user_id', $userId)
                ->exists();
        }

        return false;
    }

    /**
     * Abort with 403 unless the current user may manage the given auction.
     */
    protected function assertCanManageAuction(string $modelClass, $id, ?string $assignedListingType = null): void
    {
        if (! $this->userCanManageAuction($modelClass, $id, $assignedListingType)) {
            abort(403, 'You are not authorized to access this listing.');
        }
    }
}
