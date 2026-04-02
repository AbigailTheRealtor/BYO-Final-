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
    public function store(Request $request)
    {
        $counter = new LandlordCounterTerm();
        $counter->landlord_auction_id = $request->landlordId;
        $counter->timeframe = $request->timeframe;
        $counter->commission = $request->commission;
        $counter->agentCommission = $request->agentCommission;
        $counter->services = json_encode($request->services);
        $counter->other_services = $request->other_services;
        $counter->additional_details = $request->additionalDetails;
        $counter->save();
        return redirect('landlord/hire/agent/auctions/list')->with('success', 'Countered Terms Added Successfully!');
    }
    // public function edit(Request $request, $id)
    // {
    //     $counter = LandlordCounterTerm::where('landlord_auction_id', $id)->first();
    //     return view('landlord_counter_terms/edit', compact('counter'));
    // }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $counter = LandlordCounterTerm::findOrFail($id);
        // Update the attributes
        $propFeeOpt = '';
        $propFeeOther = '';
        $propFee = '';
        if ($request->propFeeOpt != 'Yes') {
            $propFeeOpt = 'No'; // Set to empty string
            $propFeeOther = '';
            $propFee = '';
        } else {
            $propFeeOpt = $request->propFeeOpt;
            $propFeeOther = $request->propFeeOther;
            $propFee = $request->propFee;
        }
        $counter->update([
            'landlord_auction_id' => $counter->landlord_auction_id,
            'timeframe' => ($request->timeframe != '' ? $request->timeframe : $counter->timeframe),
            'commission' => ($request->commission != '' ? $request->commission : $counter->commission),
            'services' => json_encode($request->services),
            'other_services' => ($request->other_services),
            'additional_details' => ($request->additional_details),
        ]);

        // Optionally, you can save the updated instance
        $counter->save();

        return redirect('landlord/hire/agent/auctions/list')->with('success', 'Countered Terms Has Been Updated Successfuly!');
    }
}
