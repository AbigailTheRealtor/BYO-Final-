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
use App\Models\CounterTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CounteredTerms extends Controller
{
    public function add(Request $request, $id)
    {
        $buyerId = $id;
        return view('counter_terms.add', compact('buyerId'));
        // $counter = new CounterTerm();
        // $counter->buyer_auction_id = $id;
        // $counter->save();
    }
    public function store(Request $request)
    {
        // Authorization (HIGH-5): only a party to the auction may submit counter
        // terms — the listing owner, or an agent who has bid on the listing.
        $auction = \App\Models\BuyerAgentAuction::find($request->buyerId);
        abort_unless(auth()->check() && $auction && (
            (int) $auction->user_id === (int) auth()->id() ||
            \App\Models\BuyerAgentAuctionBid::where('buyer_agent_auction_id', $auction->id)->where('user_id', auth()->id())->exists()
        ), 403);

        $counter = new CounterTerm();
        $counter->buyer_auction_id = $request->buyerId;
        $counter->timeframe = $request->timeframe;
        $counter->commission = $request->commission;
        $counter->commissionOpt = $request->commissionOpt;
        $counter->compensation = $request->compensation;
        $counter->services = json_encode($request->services);
        $counter->additionalDetails = $request->additionalDetails;
        $counter->status = 1;
        $counter->save();
        return redirect('buyer/hire/agent/auctions')->with('success', 'Countered Terms Added Successfully!');
    }
    public function edit(Request $request, $id)
    {
        $counter = CounterTerm::where('buyer_auction_id', $id)->first();
        return view('counter_terms/edit', compact('counter'));
    }
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $counter = CounterTerm::findOrFail($id);
        // Authorization (HIGH-5): only the listing owner or a bidding agent may update.
        $auction = \App\Models\BuyerAgentAuction::find($counter->buyer_auction_id);
        abort_unless(auth()->check() && $auction && (
            (int) $auction->user_id === (int) auth()->id() ||
            \App\Models\BuyerAgentAuctionBid::where('buyer_agent_auction_id', $auction->id)->where('user_id', auth()->id())->exists()
        ), 403);
        $otherCom = '';
        $commissionOpt = '';
        if ($request->commission != 'Other') {
            $otherCom = ''; // Set to empty string
        } else {
            $otherCom = $request->otherCommission; // Set to value of OtherCommission
        }
        // Update the attributes
        $counter->update([
            'timeframe' => ($request->timeframe != '' ? $request->timeframe : $counter->timeframe),
            'commission' => ($request->commission != '' ? $request->commission : $counter->commission),
            'commissionOpt' => ($request->commissionOpt != '' ?  $request->commissionOpt : $counter->commissionOpt),
            'compensation' => ($request->compensation != '' ? $request->compensation : $counter->compensation),
            'otherCommission' => ($otherCom != '' ? $otherCom : $counter->otherCommission),
            'otherComOptions' => ($request->otherComOptions != '' ? $request->otherComOptions : $counter->otherComOptions),
            'services' => ($request->services != '' ? json_encode($request->services) : $counter->services),
            'serviceOther' => ($request->serviceOther != '' ? $request->serviceOther : $counter->serviceOther),
            'additionalDetails' => ($request->additionalDetails != '' ? $request->additionalDetails : $counter->additionalDetails),
            'status' => ($request->status != '' ? $request->status : $counter->status),
        ]);

        // Optionally, you can save the updated instance
        $counter->save();

        return redirect('buyer/hire/agent/auctions')->with('success', 'Countered Terms Has Been Updated Successfuly!');
    }
}
