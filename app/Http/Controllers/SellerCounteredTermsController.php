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
    public function store(Request $request)
    {
        $counter = new SellerCounterTerm();
        $counter->seller_auction_id = $request->sellerId;
        $counter->timeframe = $request->timeframe;
        $counter->commission = $request->commission;
        $counter->sellerCommission = $request->sellerCommission;
        $counter->services = json_encode($request->services);
        $counter->other_services = $request->other_services;
        $counter->additionalDetails = $request->additionalDetails;
        $counter->status = 1;
        $counter->save();
        return redirect('hire/agent/seller/list')->with('success', 'Countered Terms Added Successfully!');
    }
    public function edit(Request $request, $id)
    {
        $pab = SellerAgentAuctionBid::findOrFail($id);
        $bid_id = $id;

        return view('seller_counter_terms.add', compact('pab', 'bid_id'));
    }
    public function update(Request $request, $id)
    {
        $counter = SellerCounterTerm::findOrFail($id);
        // Update the attributes
        $sellerCommission = '';
        if ($request->sellerCommission != 'Yes') {
            $sellerCommission = 'No'; // Set to empty strin
        } else {
            $sellerCommission = $request->sellerCommission;
        }
        $counter->update([
            'seller_auction_id' => $counter->seller_auction_id,
            'timeframe' => ($request->timeframe != '' ? $request->timeframe :   $counter->timeframe),
            'commission' => ($request->commission != '' ? $request->commission : $counter->commission),
            'sellerCommission' => ($sellerCommission != '' ? $sellerCommission : $counter->sellerCommission),
            'services' => ($request->services != '' ? json_encode($request->services) : $counter->services),
            'other_services' => ($request->other_services != '' ? $request->other_services : $counter->other_services),
            'additionalDetails' => ($request->additionalDetails),
            'status' => ($request->status != '' ? $request->status : $counter->status),
        ]);

        // Optionally, you can save the updated instance
        $counter->save();
        if ($request->status != '') {
            return redirect('hire/agent/seller/list')->with('success', 'Countered Terms Status Hase Been Changed Successfully!');
        } else {
            return redirect('hire/agent/seller/list')->with('success', 'Countered Terms Has Been Updated Successfuly!');
        }
    }
}
