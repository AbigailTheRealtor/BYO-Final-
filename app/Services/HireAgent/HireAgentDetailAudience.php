<?php

namespace App\Services\HireAgent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Which AUDIENCE is reading this Hire Agent listing — the consumer side, or an agent?
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * The redesigned detail page shows two sections to agents that it shows to nobody else:
 * Referral & Cooperation Terms, and Agent Credentials & Contact Info. Both are agent-to-agent
 * business — a referral fee and the counterparty's licence details — and both render today for
 * every visitor including anonymous ones. Gating them NARROWS what is published, which makes
 * this an authorization decision wearing a layout decision's clothes.
 *
 * So it is decided HERE, server-side, and the four controllers resolve it beside the proposal
 * access they already resolve. A view renders what it was handed and asks no question of its own.
 *
 * THE ALTERNATIVE WAS ALREADY IN THE CODEBASE AND IS THE REASON THIS EXISTS. The buyer detail
 * view carries `in_array(auth()->user()->user_type ?? '', ['agent'])` inline in Blade, computed
 * beside the markup that consumes it. That is the shape of bug the M4 hero shipped: a second
 * opinion about a rule, living next to a view, drifting from the rule everything else uses. A
 * nav bar is where such a drift becomes a disclosure, because the bar names the section it links
 * to — so an audience test living in markup would be one edit away from advertising a section by
 * name to the audience it was written to withhold it from.
 *
 * WHAT COUNTS AS AN AGENT USER, AND WHY IT IS THREE VALUES RATHER THAN ONE
 * -----------------------------------------------------------------------
 * `users_user_type_check` permits exactly seven values, and THREE of them are agents:
 * 'agent', 'buyer_agent' and 'seller_agent'. The obvious check — `user_type === 'agent'` — is a
 * near-miss rather than a rule: it silently demotes buyer_agent and seller_agent to consumers,
 * and the codebase already treats those two as agents elsewhere (BuyerAgentAuctionBidCounter
 * routes on `in_array($type, ['seller_agent', 'buyer_agent'])`). A near-miss that fails CLOSED is
 * still wrong; it would make the agent sections unreachable for real agents while looking correct.
 *
 * 'admin' IS NOT AN AGENT. No administrator path exists here, exactly as HireAgentProposalAccess
 * grants administrators nothing, and inventing one inside an audience helper would be granting
 * access from the wrong place. An admin reads this page as a consumer.
 *
 * WHAT COUNTS AS A QUALIFYING RELATIONSHIP
 * ---------------------------------------
 * Being an agent is NOT sufficient — that was decided explicitly, because a bare user_type check
 * would show every agent on the platform the referral terms and contact details attached to every
 * request they happen to open. The viewer must also be attached to THIS listing, by one of two
 * relationships:
 *
 *   1. They own it, and they are an agent. This is the agent-posted request: the Owner Info
 *      heading already flips to "Agent's Info" for it, so the page has always modelled it.
 *   2. They have submitted a proposal on it.
 *
 * A HIRED AGENT NEEDS NO THIRD BRANCH. An accepted proposal is still a proposal, and the row is
 * not deleted on acceptance, so (2) already covers the hired agent — with the useful property
 * that it keeps covering them if acceptance is later reversed.
 *
 * `user_agents` IS DELIBERATELY NOT CONSULTED. It links an agent to a CLIENT, not to a listing,
 * so reading it here would mean "an agent hired by this client on any listing may read this
 * listing's referral terms". That is a relationship to a person, not to the request being viewed,
 * and it is a wider grant than the one that was asked for.
 *
 * DENY IS THE DEFAULT AND THE FALLTHROUGH, not a branch — the same shape as the sibling service.
 *
 * ORDERING INDEPENDENCE, WHICH IS NOT COSMETIC. The proposal test issues its own query rather
 * than reading `$listing->bids`. The controllers call
 * HireAgentProposalAccess::restrictLoadedProposals() on the same request, and that REPLACES the
 * loaded relation with the authorized subset — so a class reading the loaded relation would
 * answer differently depending on whether it ran before or after that call. The query also only
 * ever asks about the viewer's OWN proposal, so it can disclose nothing about anyone else's and
 * needs no narrowing to be safe.
 *
 * @see HireAgentProposalAccess — the sibling decision, same directory, same contract.
 */
class HireAgentDetailAudience
{
    /**
     * The audience names.
     *
     * Strings rather than a bool because the section registry keys on them, and because
     * `audience === 'agent'` reads as a fact about the reader where `$isAgent === true` reads as
     * a fact about the person. A third audience is a real possibility later; a boolean would have
     * to be replaced to admit one.
     */
    public const AUDIENCE_AGENT    = 'agent';
    public const AUDIENCE_CONSUMER = 'consumer';

    /**
     * Every `user_type` this application considers an agent.
     *
     * Read off the `users_user_type_check` constraint rather than from memory. If a fourth agent
     * type is ever added to that constraint it must be added here in the same change, and
     * HireAgentDetailAudienceTest asserts this list against the constraint's own vocabulary so
     * the omission fails loudly rather than quietly demoting a class of agent to a consumer.
     */
    public const AGENT_USER_TYPES = ['agent', 'buyer_agent', 'seller_agent'];

    /**
     * The audience this viewer belongs to for this listing.
     *
     * The primary entry point. Callers that want to branch read this; the boolean below exists
     * for the call sites where a condition reads better than a comparison.
     */
    public function audienceFor(?User $viewer, ?Model $listing): string
    {
        return $this->isAgentAudience($viewer, $listing)
            ? self::AUDIENCE_AGENT
            : self::AUDIENCE_CONSUMER;
    }

    /**
     * Is this viewer an agent WITH a qualifying relationship to this listing?
     *
     * Both halves are required and neither is sufficient. A guest, a consumer, an administrator,
     * and an agent unconnected to this listing all get false — and they get it by falling through
     * rather than by being enumerated, so a viewer class nobody thought of is denied by default.
     */
    public function isAgentAudience(?User $viewer, ?Model $listing): bool
    {
        if ($viewer === null || $listing === null) {
            return false;
        }

        if (! $this->isAgentUser($viewer)) {
            return false;
        }

        return $this->ownsListing($viewer, $listing)
            || $this->hasSubmittedProposal($viewer, $listing);
    }

    /**
     * Does this user's `user_type` name an agent?
     *
     * PUBLIC, because it is a genuine question distinct from the audience one and the tests ask
     * it directly — but it is NOT a gate, for the same reason HireAgentDetailRedesign::enabled()
     * is not one. Anything deciding what to render wants isAgentAudience(); this alone is the
     * bare user_type check that was explicitly rejected as insufficient.
     *
     * Strict comparison against the list, with no normalisation, no aliasing and no prefix
     * matching: a value either names an agent or it does not, and something close to 'agent'
     * must fail rather than resolve to it.
     */
    public function isAgentUser(?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        return in_array((string) $viewer->getAttribute('user_type'), self::AGENT_USER_TYPES, true);
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * Compared as integers, and a null owner is refused.
     *
     * The same defect HireAgentProposalAccess::isListingOwner() was written to close: `user_id`
     * is nullable on these tables and arrives as a string from some of them, so a loose compare
     * against a guest's 0 would make every anonymous visitor the owner of an unowned listing.
     * Guests cannot reach this method — isAgentAudience() has already refused a null viewer — but
     * the comparison is written correctly anyway rather than relying on that.
     */
    private function ownsListing(User $viewer, Model $listing): bool
    {
        $ownerId = $listing->getAttribute('user_id');

        if ($ownerId === null) {
            return false;
        }

        return (int) $ownerId === (int) $viewer->getKey();
    }

    /**
     * Has this viewer submitted a proposal on this listing?
     *
     * Role-neutral: `bids()` resolves to the seller, buyer, landlord or tenant bid relation
     * without this class naming any of them, and `user_id` is the author column on all four —
     * the same column HireAgentProposalAccess::isProposalAuthor() reads.
     *
     * An unsaved listing is refused before the query runs. `hasMany()->exists()` on a model with
     * no key would otherwise ask the database a question about a null foreign key, and the answer
     * to that question is not one this method should be interpreting.
     */
    private function hasSubmittedProposal(User $viewer, Model $listing): bool
    {
        if ($listing->getKey() === null || $viewer->getKey() === null) {
            return false;
        }

        return $listing->bids()
            ->where('user_id', $viewer->getKey())
            ->exists();
    }
}
