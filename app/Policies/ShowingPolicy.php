<?php

namespace App\Policies;

use App\Models\Showing;
use App\Models\User;
use App\Models\UserAgent;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShowingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user is the listing owner or the listing's assigned agent.
     *
     * Agent assignment is resolved via the UserAgent pivot (user_agents table).
     * This table is populated across all four bid-acceptance flows (seller, buyer,
     * landlord, tenant) when an agent's bid is accepted by the listing owner.
     * user_id = listing owner (client), agent_id = hired agent. This is confirmed
     * as the production listing-assignment relationship for Seller/Landlord listings.
     */
    private function isOwnerOrAgent(User $user, Showing $showing): bool
    {
        $auction = $showing->offerAuction;

        if (!$auction) {
            return false;
        }

        // Listing owner
        if ((int) $auction->user_id === (int) $user->id) {
            return true;
        }

        // Assigned agent: an agent hired by the listing owner
        return UserAgent::where('user_id', $auction->user_id)
            ->where('agent_id', $user->id)
            ->exists();
    }

    /**
     * Only the listing owner or assigned agent may approve.
     */
    public function approve(User $user, Showing $showing): bool
    {
        return $this->isOwnerOrAgent($user, $showing);
    }

    /**
     * Only the listing owner or assigned agent may decline.
     */
    public function decline(User $user, Showing $showing): bool
    {
        return $this->isOwnerOrAgent($user, $showing);
    }

    /**
     * The listing owner, assigned agent, OR the requester may cancel.
     */
    public function cancel(User $user, Showing $showing): bool
    {
        if ((int) $showing->requester_id === (int) $user->id) {
            return true;
        }

        return $this->isOwnerOrAgent($user, $showing);
    }

    /**
     * Only the listing owner or assigned agent may mark a showing complete.
     */
    public function complete(User $user, Showing $showing): bool
    {
        return $this->isOwnerOrAgent($user, $showing);
    }
}
