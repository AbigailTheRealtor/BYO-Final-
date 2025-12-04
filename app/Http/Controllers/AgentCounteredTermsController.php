<?php

namespace App\Http\Controllers;

use App\Models\AgentCounterTerm;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AgentCounteredTermsController extends Controller
{
    public function add(Request $request, $id)
    {
        $agentId = $id;
        return view('agent_counter_terms.add', compact('agentId'));
    }
    public function store(Request $request)
    {
        $counter = new AgentCounterTerm();
        $counter->agent_auction_id = $request->agentId;
        $counter->timeframe = $request->timeframe;
        $counter->agentFee = $request->agentFee;
        $counter->agentFeeOther = $request->agentFeeOther;
        $counter->agentCharge = $request->agentCharge;
        $counter->agentChargeOther = $request->agentChargeOther;
        $counter->services = json_encode($request->services);
        $counter->other_services = $request->other_services;
        $counter->additionalDetails = $request->additionalDetails;
        $counter->status = 1;
        $counter->save();
        return redirect()->back()->with('success', 'Countered Terms Added Successfully!');
    }
    public function edit(Request $request, $id)
    {

        $counter = AgentCounterTerm::where('agent_auction_id', $id)->first();
        return view('agent_counter_terms/edit', compact('counter'));
    }
    public function update(Request $request, $id)
    {
        // dd($request->all());
        $counter = AgentCounterTerm::findOrFail($id);
        // Update the attributes
        $agentCharge = '';
        $agentChargeOther = '';
        $agentFee = '';
        if ($request->agentCharge != 'Yes') {
            $agentCharge = 'No'; // Set to empty string
            $agentChargeOther = '';
            $agentFee = '';
        } else {
            $agentCharge = $request->agentCharge;
            $agentChargeOther = $request->agentChargeOther;
            $agentFee = $request->agentFee;
        }
        $counter->update([
            'agent_auction_id' => $counter->agent_auction_id,
            'timeframe' => ($request->timeframe != '' ? $request->timeframe : $counter->timeframe),
            'agentCharge' => ($agentCharge != '' ? $agentCharge : $counter->agentCharge),
            'agentChargeOther' => ($agentChargeOther != '' ? $agentChargeOther : $counter->agentChargeOther),
            'agentFee' => ($request->agentFee != '' ? $request->agentFee : $counter->agentFee),
            'agentFeeOther' => ($request->agentFeeOther != '' ? $request->agentFeeOther : $counter->agentFeeOther),
            'services' => ($request->services != '' ? json_encode($request->services) : $counter->services),
            'other_services' => ($request->other_services != '' ? $request->other_services : $counter->other_services),
            'additionalDetails' => ($request->additionalDetails != '' ? $request->additionalDetails : $counter->additionalDetails),
            'status' => ($request->status != '' ? $request->status : $counter->status),
        ]);

        return redirect('/landlord/auctions')->with('success', 'Countered Terms Has Been Updated Successfuly!');
    }
}
