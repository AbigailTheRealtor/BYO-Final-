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
use App\Models\SellerCounterTerm;
use App\Models\SellerAgentAuctionBid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SellerCounteredTermsController extends Controller
{
    public function add(Request $request, $id)
    {
        $pab = SellerAgentAuctionBid::with('meta', 'auction')->findOrFail($id);

        $auction = $pab->auction ?? \App\Models\SellerAgentAuction::find($pab->seller_agent_auction_id);
        if (!$auction) {
            abort(404, 'Auction not found.');
        }

        $isSeller = ($auction->user_id === Auth::id());
        $isAgent  = ($pab->user_id === Auth::id());

        // Only the listing owner (seller) may create original counter terms.
        // The bidding agent may create a counter-back (response to seller's counter).
        if (!$isSeller && !$isAgent) {
            abort(403, 'You are not authorized to submit counter terms for this bid.');
        }

        // Agent can only counter-back when a seller counter already exists
        if ($isAgent) {
            $sellerCounter = \App\Models\SellerCounterTerm::where('seller_agent_auction_bid_id', $pab->id)
                ->where('user_id', $auction->user_id)
                ->latest('updated_at')
                ->first();
            if (!$sellerCounter) {
                return redirect()->back()->with('error', 'You can only submit a counter-back after the seller has submitted counter terms.');
            }
        }

        $bid_id = $id;
        return view('seller_counter_terms.add', compact('pab', 'bid_id'));
    }
    public function edit(Request $request, $id)
    {
        // Parity with add(). Without this the edit screen admitted anyone and relied
        // entirely on the Livewire component's own mount() guard to 403 — which it
        // does, so this closes an inconsistency rather than a reachable hole, and
        // keeps the two entry points from drifting apart again.
        //
        // Deliberately NOT repeating add()'s counter-back precondition: that check
        // gates *creating* a counter-back before the seller has countered, and edit()
        // reaches an existing row. The component still applies it on mount.
        $pab = SellerAgentAuctionBid::findOrFail($id);

        $auction = \App\Models\SellerAgentAuction::find($pab->seller_agent_auction_id);
        if (!$auction) {
            abort(404, 'Auction not found.');
        }

        $isSeller = ((int) $auction->user_id === (int) Auth::id());
        $isAgent  = ((int) $pab->user_id === (int) Auth::id());

        if (!Auth::check() || (!$isSeller && !$isAgent)) {
            abort(403, 'You are not authorized to submit counter terms for this bid.');
        }

        $bid_id = $id;

        return view('seller_counter_terms.add', compact('pab', 'bid_id'));
    }

    // store() and update() were RETIRED.
    //
    // They were the last of the pre-EAV ("Gen 1") write path: they wrote
    // `seller_auction_id`, `timeframe`, `commission`, `sellerCommission`, `services`,
    // `other_services` and `additionalDetails`, none of which exist on the current
    // `seller_counter_terms` table, and never set `user_id`,
    // `seller_agent_auction_bid_id` or `seller_agent_auction_id`, all of which are
    // NOT NULL. Every call therefore ended in
    // "table seller_counter_terms has no column named seller_auction_id" and a 500 —
    // including calls from the listing owner. Nothing in the UI posted to them: the
    // add/edit screens below render the Livewire component, which is the real write
    // path and is unaffected.
    //
    // Retired rather than repaired because repairing them would have re-implemented,
    // in a second place with no users, what
    // App\Http\Livewire\Seller\SellerAgentAuctionCounterTerm::submit() already does
    // correctly against the current schema.
    //
    // @see tests/Feature/Security/CounteredTermsAuthorizationTest.php
}
