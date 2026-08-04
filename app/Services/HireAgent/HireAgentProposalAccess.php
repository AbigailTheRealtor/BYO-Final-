<?php

namespace App\Services\HireAgent;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * The authoritative access layer for Hire Agent proposal privacy.
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * Before Milestone 2 every Hire Agent detail view decided proposal visibility for itself,
 * inline, in Blade. All four views loaded the complete bid set and then tried to hide the
 * parts a viewer should not see. That is the wrong shape for a privacy rule, for three
 * reasons that were all live defects rather than hypotheticals:
 *
 *   1. Hiding is not withholding. The data reached the view, so any markup that forgot the
 *      guard leaked it — and aggregates leaked even when per-bid detail did not. A competing
 *      agent could learn a count, a rank, or a "lowest bid" from blocks whose authors were
 *      only thinking about identity.
 *   2. Four independent copies of a rule drift. CLAUDE.md's role symmetry means Seller,
 *      Buyer, Landlord and Tenant each had their own gate, and they had already diverged:
 *      the Buyer view's "last bidder" line had no viewer gate at all, while the other three
 *      had one.
 *   3. A rule expressed as a Blade condition cannot be tested except through rendered HTML,
 *      which makes the privacy contract hostage to markup changes.
 *
 * So visibility is decided HERE, server-side, before the view runs. The controllers narrow
 * the loaded relation through restrictLoadedProposals(), and the views then render whatever
 * survived. A view cannot disclose a proposal it was never handed.
 *
 * AUTHORIZATION MODEL
 * -------------------
 *   • The listing owner may review every proposal on their own listing.
 *   • A submitting agent may view their own proposal, and only their own.
 *   • A competing agent receives nothing about another proposal — not content, amount,
 *     identity, activity, rank, order, count, or summary.
 *   • Everything else is denied. Deny is the default and the fallthrough, not a branch.
 *
 * NO ADMINISTRATOR ACCESS. Approved requirement 6 names "the listing owner and authorized
 * administrators", but this checkpoint implements the owner half only: no administrator
 * review path exists today, and the authorization is explicit that current owner-only access
 * is preserved rather than widened. Adding one is a later, separately approved decision.
 * HireAgentProposalAccessTest asserts an 'admin' user gets nothing, so granting that access
 * can only ever be deliberate.
 *
 * ROLE NEUTRALITY. Nothing here names a role. The listing is any of the four Hire Agent
 * auction models and the proposal any of their bids; the owning column is read from the
 * `bids` relation itself, so the Seller/Buyer native-column tables and the
 * Landlord/Tenant EAV-backed tables are handled by the same code path. This is the one place
 * that symmetry is guaranteed rather than hoped for.
 *
 * @see docs/investigations/hire-agent-listing-framework-implementation-plan.md §2.2
 */
class HireAgentProposalAccess
{
    /**
     * Is this viewer the owner of this listing?
     *
     * Compared as integers on purpose. `$auction->user_id` arrives as a string from some of
     * these tables, and a loose `==` against a null viewer id would make a guest look like
     * the owner of a listing whose user_id is 0 or null.
     */
    public function isListingOwner(?int $viewerId, ?Model $listing): bool
    {
        if ($viewerId === null || $listing === null) {
            return false;
        }

        $ownerId = $listing->getAttribute('user_id');

        if ($ownerId === null) {
            return false;
        }

        return (int) $ownerId === $viewerId;
    }

    /**
     * May this viewer review the full proposal set for this listing?
     *
     * Owner only. This is the single authorized path to the whole set, and it is what the
     * views use to decide whether the owner-only empty state ("No agents have submitted a
     * bid yet.") may be rendered — a message that is itself a count disclosure, and so is
     * owner-only rather than public.
     */
    public function canReviewAllProposals(?int $viewerId, ?Model $listing): bool
    {
        return $this->isListingOwner($viewerId, $listing);
    }

    /**
     * May this viewer see this one proposal?
     *
     * Two allow branches — owner of the listing, or author of the proposal — then deny.
     * The proposal is also confirmed to belong to the listing being asked about, so a caller
     * cannot authorize an arbitrary bid id by pairing it with a listing they happen to own.
     */
    public function canViewProposal(?int $viewerId, ?Model $listing, ?Model $proposal): bool
    {
        if ($viewerId === null || $listing === null || $proposal === null) {
            return false;
        }

        if (! $this->proposalBelongsToListing($listing, $proposal)) {
            return false;
        }

        if ($this->isListingOwner($viewerId, $listing)) {
            return true;
        }

        if ($this->isProposalAuthor($viewerId, $proposal)) {
            return true;
        }

        return false;
    }

    /**
     * The proposals this viewer is authorized to see, as an Eloquent collection.
     *
     * Owner  → every proposal.
     * Agent  → their own proposal(s), nothing else.
     * Anyone else, including guests and administrators → empty.
     *
     * Reads the already-loaded relation when there is one so a detail-page render does not
     * issue an extra query per call, and falls back to a query otherwise. Either way the
     * returned set is filtered by canViewProposal(), so the two can never disagree.
     */
    public function visibleProposals(?int $viewerId, ?Model $listing): EloquentCollection
    {
        if ($listing === null) {
            return new EloquentCollection();
        }

        $all = $listing->relationLoaded('bids')
            ? $listing->getRelation('bids')
            : $listing->bids()->get();

        if ($viewerId === null) {
            return new EloquentCollection();
        }

        $visible = $all->filter(
            fn ($proposal) => $this->canViewProposal($viewerId, $listing, $proposal)
        )->values();

        return new EloquentCollection($visible->all());
    }

    /**
     * Narrow the listing's LOADED `bids` relation in place to the authorized subset.
     *
     * This is the seam the four Hire Agent controllers use, and it is what makes the privacy
     * rule structural: after this call the view holds no unauthorized row, so it cannot leak
     * one through markup that predates — or postdates — the rule.
     *
     * Deliberately preserves the viewer's own proposal, because `$my_bid` and `$userHasBid`
     * are read off this same relation and an agent must not lose sight of their own bid.
     */
    public function restrictLoadedProposals(?int $viewerId, ?Model $listing): void
    {
        if ($listing === null) {
            return;
        }

        $listing->setRelation('bids', $this->visibleProposals($viewerId, $listing));
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function isProposalAuthor(int $viewerId, Model $proposal): bool
    {
        $authorId = $proposal->getAttribute('user_id');

        if ($authorId === null) {
            return false;
        }

        return (int) $authorId === $viewerId;
    }

    /**
     * Confirm the proposal really hangs off this listing.
     *
     * The owning column is read from the `bids` relation rather than hardcoded, which is what
     * keeps this class role-neutral: it resolves to seller_agent_auction_id,
     * buyer_agent_auction_id, landlord_agent_auction_id or tenant_agent_auction_id without
     * naming any of them.
     */
    private function proposalBelongsToListing(Model $listing, Model $proposal): bool
    {
        $foreignKey = $listing->bids()->getForeignKeyName();

        $ownerKey = $proposal->getAttribute($foreignKey);

        if ($ownerKey === null) {
            return false;
        }

        return (int) $ownerKey === (int) $listing->getKey();
    }
}
