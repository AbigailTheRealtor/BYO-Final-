<?php

namespace App\Http\Controllers;

use App\Enums\ShowingStatus;
use App\Http\Requests\StoreShowingRequest;
use App\Models\OfferAuction;
use App\Models\Showing;
use Illuminate\Support\Facades\Auth;

class ShowingController extends Controller
{
    public function store(StoreShowingRequest $request)
    {
        // StoreShowingRequest already validates offer_auction_id exists in offer_auctions,
        // so a null result here means the listing exists but is not showing-eligible
        // (i.e. not a seller or landlord offer listing per the scope from #2089).
        $listing = OfferAuction::showingEligible()->with('metas')->find($request->offer_auction_id);

        if (!$listing) {
            abort(403, 'Showing requests are only available for seller and landlord offer listings.');
        }

        if ((int) $listing->user_id === (int) Auth::id()) {
            abort(403, 'You cannot request a showing on your own listing.');
        }

        $assignedAgentId = (int) ($listing->metas->where('meta_key', 'hired_agent_id')->first()?->meta_value ?? 0);
        if ($assignedAgentId && $assignedAgentId === (int) Auth::id()) {
            abort(403, 'Assigned agents cannot request a showing on a listing they are managing.');
        }

        Showing::create([
            'offer_auction_id'     => $listing->id,
            'requester_id'         => Auth::id(),
            'requested_date'       => $request->requested_date,
            'requested_start_time' => $request->requested_start_time,
            'requested_end_time'   => $request->requested_end_time,
            'requester_message'    => $request->requester_message,
            'status'               => ShowingStatus::REQUESTED,
        ]);

        return redirect()->back()->with('success', 'Your showing request has been submitted. The listing owner will be in touch to confirm.');
    }

    public function index()
    {
        $showings = Showing::with(['offerAuction.metas'])
            ->where('requester_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('showings.index', compact('showings'));
    }
}
