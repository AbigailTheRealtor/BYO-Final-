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
use App\Models\TenantCounterTerm;
use App\Models\TenantAgentAuction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BuyerCounteredTermsController extends Controller
{
    public function add(Request $request, $id)
    {
        $pab = BuyerAgentAuction::whereId($id)->first();
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;


        return view('buyer_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }


    public function edit(Request $request, $id)
    {
        $pab = BuyerAgentAuction::findOrFail($id);
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ?: null;

        // reuse the same Blade + Livewire; Livewire will detect existing data
        return view('buyer_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }


    public function store(Request $request)
    {
        $counter = new TenantCounterTerm();
        $counter->tenant_auction_id = $request->tanantId;
        $counter->timeframe = $request->timeframe;
        $counter->propFeeOpt = $request->propFeeOpt;
        $counter->propFee = $request->propFee;
        $counter->propFeeOther = $request->propFeeOther;
        $counter->services = json_encode($request->services);
        $counter->other_services = $request->other_services;
        $counter->additionalDetails = $request->additionalDetails;
        $counter->status = 1;
        $counter->save();
        return redirect('tenant/hire/agent/auctions/list')->with('success', 'Countered Terms Added Successfully!');
    }
    // public function edit(Request $request, $id)
    // {

    //     $counter = TenantCounterTerm::where('tenant_auction_id', $id)->first();
    //     return view('tenant_counter_terms/edit', compact('counter'));
    // }
    public function update(Request $request, $id)
    {
        $counter = TenantCounterTerm::findOrFail($id);
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
            'tenant_auction_id' => $counter->tenant_auction_id,
            'timeframe' => ($request->timeframe != '' ? $request->timeframe : $counter->timeframe),
            'propFeeOpt' => ($propFeeOpt != '' ? $propFeeOpt : $counter->propFeeOpt),
            'propFee' => ($propFee != '' ? $propFee : $counter->propFee),
            'propFeeOther' => ($propFeeOther != '' ? $propFeeOther : $counter->propFeeOther),
            'services' => ($request->services != '' ? json_encode($request->services) : $counter->services),
            'other_services' => ($request->other_services != '' ? $request->other_services : $counter->other_services),
            'additionalDetails' => ($request->additionalDetails != '' ? $request->additionalDetails : $counter->additionalDetails),
            'status' => ($request->status != '' ? $request->status : $counter->status),
        ]);

        // Optionally, you can save the updated instance
        $counter->save();

        return redirect('tenant/hire/agent/auctions/list')->with('success', 'Countered Terms Has Been Updated Successfuly!');
    }
}
