<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\City;
use App\Models\State;
use App\Models\County;
use App\Models\Bedroom;
use App\Models\Country;
use App\Models\Bathroom;
use App\Models\Financing;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordCounterTerm;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LandlordCounteredTermsController extends Controller
{
    /**
     * Re-resolve the bid and its auction from the database and admit only a party
     * to that negotiation — the listing owner or the bidding agent.
     *
     * Always re-queries rather than reading `$pab->auction`, because that relation
     * is declared `->withDefault()` — it hands back an empty model instead of null
     * when the auction is missing, so a `!$auction` check there can never fire and
     * an absent auction would silently read as "owned by nobody" rather than as a 404.
     *
     * Without this, any authenticated user could pass a foreign bid id in the URL
     * and reach the live counter-terms screen for someone else's negotiation. The
     * route group carries only `auth` + `verified` — the `landlordAuth` middleware
     * on this prefix is commented out — so authentication was the ONLY barrier.
     *
     * This is defence in depth, not the authorization boundary:
     * App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm re-authorizes
     * independently, because a Livewire component can be mounted from anywhere and
     * its actions arrive on later requests that never pass through this controller.
     */
    private function bidForParty($id): LandlordAgentAuctionBid
    {
        $pab = LandlordAgentAuctionBid::find($id);
        if (!$pab) {
            abort(404);
        }

        $auction = LandlordAgentAuction::find($pab->landlord_agent_auction_id);
        if (!$auction) {
            abort(404);
        }

        $isLandlord = (int) $auction->user_id === (int) Auth::id();
        $isAgent    = (int) $pab->user_id === (int) Auth::id();

        abort_unless(Auth::check() && ($isLandlord || $isAgent), 403, 'You are not authorized to view counter terms for this bid.');

        return $pab;
    }

    public function add(Request $request, $id)
    {
        $pab = $this->bidForParty($id);
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('landlord_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }
    public function edit(Request $request, $id)
    {
        $pab = $this->bidForParty($id);
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ?: null;

        return view('landlord_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }
    // store() and update() were RETIRED.
    //
    // Same Gen-1 breakage as the Seller controller — they wrote `landlord_auction_id`,
    // `timeframe`, `commission`, `agentCommission`, `services`, `other_services` and
    // `additional_details`, none of which exist on `landlord_counter_terms` — so every
    // call 500'd on "no such column: landlord_auction_id", the listing owner's included.
    // No UI posted to either.
    //
    // update() additionally carried a real authorization defect. It resolved
    //
    //     LandlordAgentAuction::find($counter->landlord_agent_auction_id)
    //
    // but that column holds a BID id (its FK references landlord_agent_auction_bids.id;
    // the name is historical and is deliberately NOT being renamed). So the ownership
    // check was run against whatever auction happened to share that number: an attacker
    // owning the auction whose id equalled the victim's bid id PASSED the check, and the
    // legitimate listing owner was denied. Unauthorized mutation was prevented only
    // because the Gen-1 UPDATE then crashed — meaning a well-meaning "fix the columns"
    // change would have converted it into a live foreign write.
    //
    // Retirement removes the endpoint, so the collision no longer reaches a handler at
    // all. The correct counter → bid → auction resolution already exists in
    // LandlordAgentAuctionBidController::accept_counter_bid(), and the working write path
    // is App\Http\Livewire\Landlord\LandlordAgentAuctionCounterTerm.
    //
    // @see tests/Feature/Security/CounteredTermsAuthorizationTest.php
    //      ::test_landlord_counter_term_id_collision_no_longer_reaches_a_handler
}
