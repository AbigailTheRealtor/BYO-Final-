<?php

namespace App\Support\Product;

use App\Models\User;

/**
 * Where "start hiring an agent" goes, for one viewer.
 *
 * Exists because a BidYourAgent deployment cannot have a primary action that
 * leads into BidYourOffer, and the global "+" in both public layouts did exactly
 * that — it pointed at `add-listing`, the legacy Create Property Listing wizard,
 * for every visitor including signed-out ones.
 *
 * The role→route mapping itself is not new; it is the same one the header, the
 * home hero and the sidenav already spell out inline. This is the copy the
 * product-aware entry points read, so a future consolidation has somewhere to
 * consolidate onto. Nothing here is a redesign: the destinations are the ones
 * those surfaces already use.
 */
final class HireAgentEntryPoints
{
    /**
     * A signed-out visitor is sent to register rather than to a Hire Agent form:
     * every one of these flows requires an account, and a login wall reached from
     * a "Hire Agent" button reads as a dead end.
     */
    public static function forUser(?User $user): string
    {
        if ($user === null) {
            return route('register');
        }

        switch ($user->user_type) {
            case 'seller':
                return route('sellerAgentHireAuction');

            case 'buyer':
                return route('buyer.add-auction');

            case 'landlord':
                return route('landlord.hire.agent.auction');

            case 'tenant':
                return route('hire.agent.auction', ['user_type' => 'tenant']);

            case 'agent':
                // An agent creates Hire Agent listings for any of the four roles.
                // One link cannot express that choice; the hub page does.
                return route('agent.hire-listings');

            default:
                return route('dashboard');
        }
    }

    public static function forCurrentUser(): string
    {
        return self::forUser(auth()->user());
    }
}
