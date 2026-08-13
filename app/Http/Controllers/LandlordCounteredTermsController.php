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
    public function add(Request $request, $id)
    {
        $pab = LandlordAgentAuctionBid::whereId($id)->first();
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('landlord_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }
    public function edit(Request $request, $id)
    {
        $pab = LandlordAgentAuctionBid::findOrFail($id);
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
