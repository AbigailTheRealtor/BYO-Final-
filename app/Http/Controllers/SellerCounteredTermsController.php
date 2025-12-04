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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SellerCounteredTermsController extends Controller
{
    public function add(Request $request, $id)
    {
        $sellerId = $id;
        return view('seller_counter_terms.add', compact('sellerId'));
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

        $counter = SellerCounterTerm::where('seller_auction_id', $id)->first();
        return view('seller_counter_terms/edit', compact('counter'));
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
