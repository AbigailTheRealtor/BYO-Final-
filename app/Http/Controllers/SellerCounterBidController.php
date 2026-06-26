<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SellerAgentAuction;
use App\Models\PropertyAuctionBid;
use App\Models\SellerAgentAuctionBid;
use App\Models\User;
use Auth;

class SellerCounterBidController extends Controller
{
    public function addListing(Request $request)
    {
    }
    public function store(Request $request)
    {
        $dataa = SellerAgentAuctionBid::with('meta')->find($request->bid_id);
        $bid = new SellerAgentAuctionBid();
        // $bidDetails = SellerAgentAuctionBid::where('property_auction_id', $request->auction_id)->max('price');
        $counterBidmax = SellerAgentAuctionBid::where('seller_counter_id', $request->bid_id)->max('price');
        // if ($request->counterAmount >= $bidDetails) {
        if ($request->counterAmount >= $counterBidmax) {
            $bid->user_id = $dataa->user_id;
            $bid->seller_counter_id = $request->bid_id;
            $bid->seller_agent_auction_id = $dataa->seller_agent_auction_id;
            $bid->price = $request->counterAmount;
            $bid->save();
            $lastInsertedId = $bid->id;
            $auction = SellerAgentAuction::whereId($request->auction_id)->first();
            $data = SellerAgentAuction::whereId($request->auction_id)->first();
            $user = User::where('id', $auction->user_id)->first();
            $counterView = SellerAgentAuctionBid::with('meta')->find($lastInsertedId);
            // Redirecting back with query parameters
            return redirect()->back()->with(['success' => 'Your Counter Bid Has Been Added!', 'data' => $data, 'auction' => $auction, 'user' => $user]);
        } else {
            return redirect()->back()->with('error', 'Your Offered Price Should be higher than the last Bid!');
        }
    }

    public function edit()
    {
    }

    public function update()
    {
    }

    public function list()
    {
    }


    public function viewPropertyListing()
    {
    }

    public function destroyCounter($id)
    {
        $counter = SellerAgentAuctionBid::findOrFail($id);

        // Authorization (Phase 1): only a party to the auction may reject/delete
        // this counter — the listing owner or the bidding agent. Prevents IDOR
        // deletion of arbitrary bids by ID.
        $auction = SellerAgentAuction::find($counter->seller_agent_auction_id);
        $authorized = Auth::check() && $auction && (
            (int) $auction->user_id === (int) Auth::id() ||
            (int) $counter->user_id === (int) Auth::id()
        );
        abort_unless($authorized, 403);

        $counter->delete();
        return redirect()->back()->with('success', 'Counter Bid Has Been Rejected!');
    }
}
