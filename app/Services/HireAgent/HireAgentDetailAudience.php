<?php

namespace App\Services\HireAgent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Which AUDIENCE is reading this Hire Agent listing — the public, its owner, or an agent?
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * This page is two things at once, and that is the whole difficulty. It is a PUBLIC listing — the
 * route carries `web` and no auth, so anonymous visitors reach it — and it is also the host of a
 * PRIVATE bid workflow, where a client reads proposals and accepts, rejects or counters them.
 * Public-website visibility and private bid visibility are not the same question, and a page that
 * answers only one of them either publishes what it should not or withholds what its owner needs.
 *
 * So there are four sections whose audience is narrower than the page:
 *
 *   · Services and Broker Compensation — what proposals are evaluated against. The client needs
 *     them; a passer-by has no business with them. Today Services is fully public and compensation
 *     sits behind a bare Auth::check(), which admits any logged-in stranger.
 *   · Referral & Cooperation Terms and Agent Credentials & Contact Info — agent-to-agent business,
 *     a referral fee and the counterparty's licence details. Both render for every visitor today.
 *
 * Every one of those is a NARROWING, which makes this an authorization decision wearing a layout
 * decision's clothes.
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
     * The audience names, in increasing order of what they may read.
     *
     * THERE ARE THREE, AND THE THIRD ARRIVED BECAUSE TWO COULD NOT EXPRESS THE RULE. The first
     * draft had 'consumer' and 'agent', which conflated two viewers who are not alike: an
     * anonymous passer-by, and the client evaluating proposals on their own request. Those two
     * need different pages. The Hire Agent detail page is publicly reachable — its route carries
     * `web` and no auth — while also hosting the private bid workflow, so "not an agent" covers
     * both a stranger and the person the proposals were written for.
     *
     *   · public — anonymous, or authenticated with no connection to this listing. Sees the
     *              request: what is wanted, where, on what terms.
     *   · owner  — the client who posted it and receives the proposals. Sees the above plus the
     *              Services and Broker Compensation they are evaluating bids against.
     *   · agent  — an agent with a relationship to it. Sees everything the owner sees, plus the
     *              agent-to-agent appendix: referral terms and the counterparty's credentials.
     *
     * STRICTLY NESTED, and the registry depends on it: public ⊂ owner ⊂ agent. Nothing is shown to
     * a narrower audience and withheld from a wider one, so a section's audience names the LOWEST
     * tier that may read it and every tier above inherits.
     */
    public const AUDIENCE_PUBLIC = 'public';
    public const AUDIENCE_OWNER  = 'owner';
    public const AUDIENCE_AGENT  = 'agent';

    /**
     * Every `user_type` this application considers an agent.
     *
     * Read off the `users_user_type_check` constraint rather than from memory. If a fourth agent
     * type is ever added to that constraint it must be added here in the same change, and
     * HireAgentDetailAudienceTest asserts this list against the constraint's own vocabulary so
     * the omission fails loudly rather than quietly demoting a class of agent to a consumer.
     */
    public const AGENT_USER_TYPES = ['agent', 'buyer_agent', 'seller_agent'];

    public function __construct(private HireAgentProposalAccess $proposalAccess)
    {
    }

    /**
     * The audience this viewer belongs to for this listing.
     *
     * The primary entry point. Callers that want to branch read this; the booleans below exist for
     * the call sites where a condition reads better than a comparison.
     *
     * WIDEST MATCH WINS, AND THE ORDER OF THESE TWO CHECKS IS THE WHOLE RULE. An agent who posted
     * the request satisfies both — they own it, and they are an agent with a relationship to it —
     * and they must resolve to `agent`, the wider tier, or the referral terms and credentials on
     * their own agent-to-agent listing would be withheld from them. Testing ownership first would
     * do exactly that, silently, for the one viewer the agent sections exist to serve.
     */
    public function audienceFor(?User $viewer, ?Model $listing): string
    {
        if ($this->isAgentAudience($viewer, $listing)) {
            return self::AUDIENCE_AGENT;
        }

        if ($this->isOwnerAudience($viewer, $listing)) {
            return self::AUDIENCE_OWNER;
        }

        return self::AUDIENCE_PUBLIC;
    }

    /**
     * Is this viewer the client who posted the request?
     *
     * NO USER TYPE TEST, deliberately. Ownership is the relationship that matters here — the
     * person receiving the proposals is the person who may read what they are being evaluated
     * against, whatever their account says they are. An agent who owns the listing is caught by
     * the agent branch above before reaching this one.
     *
     * DELEGATED RATHER THAN REIMPLEMENTED. HireAgentProposalAccess has answered "is this viewer
     * the owner" correctly since M5.4 — it refuses a null viewer, refuses a null owner, and
     * compares as integers — and the reason the four detail views once had two subtly different
     * copies of that test is that copies are what happens. A second correct implementation is
     * still a second implementation.
     */
    public function isOwnerAudience(?User $viewer, ?Model $listing): bool
    {
        if ($viewer === null || $listing === null) {
            return false;
        }

        return $this->proposalAccess->isListingOwner((int) $viewer->getKey(), $listing);
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

        return $this->isOwnerAudience($viewer, $listing)
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
