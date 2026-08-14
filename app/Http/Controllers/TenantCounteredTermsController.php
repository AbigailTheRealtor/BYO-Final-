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
use App\Models\TenantAgentAuctionBid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TenantCounteredTermsController extends Controller
{
    /**
     * Resolve the bid, then the auction it belongs to, and admit only a party to
     * that negotiation: the listing owner, or the agent who placed the bid.
     *
     * The auction is re-resolved from the database by id rather than read through
     * `$pab->auction`, because that relation is declared `->withDefault()` — it
     * hands back an empty model instead of null when the auction is missing, so a
     * `!$auction` check there can never fire and an absent auction would silently
     * read as "owned by nobody" rather than as a 404.
     *
     * Without this, any authenticated user could pass a foreign bid id in the URL
     * and reach the live counter-terms screen for someone else's negotiation.
     */
    private function bidForParty($id): TenantAgentAuctionBid
    {
        $pab = TenantAgentAuctionBid::find($id);
        if (!$pab) {
            abort(404);
        }

        $auction = TenantAgentAuction::find($pab->tenant_agent_auction_id);
        if (!$auction) {
            abort(404);
        }

        $isTenant = (int) $auction->user_id === (int) Auth::id();
        $isAgent  = (int) $pab->user_id === (int) Auth::id();

        abort_unless(Auth::check() && ($isTenant || $isAgent), 403, 'You are not authorized to view counter terms for this bid.');

        return $pab;
    }

    public function add(Request $request, $id)
    {
        $pab = $this->bidForParty($id);
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ? $request->counter_bid_id : null;

        return view('tenant_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }


  public function edit(Request $request, $id)
    {
        $pab = $this->bidForParty($id);
        $bid_id = $id;
        $parent_counter_id = $request->counter_bid_id ?: null;

        // reuse the same Blade + Livewire; Livewire will detect existing data
        return view('tenant_counter_terms.add', compact('bid_id', 'pab', 'parent_counter_id'));
    }

    
    public function store(Request $request)
    {
        // Authorization (HIGH-5): only a party to the auction may submit counter
        // terms — the listing owner, or an agent who has bid on the listing.
        $auction = \App\Models\TenantAgentAuction::find($request->tanantId);
        abort_unless(auth()->check() && $auction && (
            (int) $auction->user_id === (int) auth()->id() ||
            \App\Models\TenantAgentAuctionBid::where('tenant_agent_auction_id', $auction->id)->where('user_id', auth()->id())->exists()
        ), 403);

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
        // Authorization (HIGH-5): only the listing owner or a bidding agent may update.
        $auction = \App\Models\TenantAgentAuction::find($counter->tenant_agent_auction_id);
        abort_unless(auth()->check() && $auction && (
            (int) $auction->user_id === (int) auth()->id() ||
            \App\Models\TenantAgentAuctionBid::where('tenant_agent_auction_id', $auction->id)->where('user_id', auth()->id())->exists()
        ), 403);
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
