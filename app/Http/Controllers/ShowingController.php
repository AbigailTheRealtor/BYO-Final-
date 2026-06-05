<?php

namespace App\Http\Controllers;

use App\Enums\ShowingStatus;
use App\Exceptions\ShowingTransitionException;
use App\Http\Requests\StoreShowingRequest;
use App\Models\OfferAuction;
use App\Models\Showing;
use App\Services\Showing\ShowingStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShowingController extends Controller
{
    public function __construct(private ShowingStatusService $service) {}

    /**
     * Store a new showing request (submitted by a prospective buyer/tenant).
     * POST /offer-listing/{role}/{auction}/showing
     */
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

    /**
     * List showings for the authenticated requester.
     * GET /my-showings
     */
    public function index()
    {
        $showings = Showing::with(['offerAuction.metas'])
            ->where('requester_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('showings.index', compact('showings'));
    }

    /**
     * Owner management view — all showings for the authenticated user's listings,
     * grouped by status.
     *
     * GET /my-showings/manage
     */
    public function manage()
    {
        $userId = Auth::id();

        $showings = Showing::with(['offerAuction', 'requester'])
            ->whereHas('offerAuction', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->paginate(20);

        $grouped = $showings->getCollection()->groupBy('status');

        return view('showings.manage', compact('showings', 'grouped'));
    }

    /**
     * Approve a showing request.
     * PATCH /showings/{showing}/approve
     */
    public function approve(Request $request, Showing $showing)
    {
        $this->authorize('approve', $showing);

        $validated = $request->validate([
            'approved_date'       => ['nullable', 'date'],
            'approved_start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'approved_end_time'   => ['nullable', 'date_format:H:i,H:i:s'],
            'owner_message'       => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->approve($showing, $validated, Auth::user());
        } catch (ShowingTransitionException $e) {
            return $this->transitionError($e);
        }

        return back()->with('success', 'Showing approved successfully.');
    }

    /**
     * Decline a showing request.
     * PATCH /showings/{showing}/decline
     */
    public function decline(Request $request, Showing $showing)
    {
        $this->authorize('decline', $showing);

        $validated = $request->validate([
            'owner_message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->decline($showing, $validated, Auth::user());
        } catch (ShowingTransitionException $e) {
            return $this->transitionError($e);
        }

        return back()->with('success', 'Showing declined.');
    }

    /**
     * Cancel a showing (owner, agent, or requester).
     * PATCH /showings/{showing}/cancel
     */
    public function cancel(Request $request, Showing $showing)
    {
        $this->authorize('cancel', $showing);

        try {
            $this->service->cancel($showing, Auth::user());
        } catch (ShowingTransitionException $e) {
            return $this->transitionError($e);
        }

        return back()->with('success', 'Showing canceled.');
    }

    /**
     * Mark a showing as completed (owner or assigned agent only).
     * PATCH /showings/{showing}/complete
     */
    public function complete(Request $request, Showing $showing)
    {
        $this->authorize('complete', $showing);

        try {
            $this->service->complete($showing, Auth::user());
        } catch (ShowingTransitionException $e) {
            return $this->transitionError($e);
        }

        return back()->with('success', 'Showing marked as completed.');
    }

    /**
     * Return a 422 Unprocessable Entity response for an illegal status transition.
     * The task spec permits "422 or redirect with an error" for invalid transitions.
     */
    private function transitionError(ShowingTransitionException $e): \Illuminate\Http\Response
    {
        if (request()->expectsJson()) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response(view('errors.showing-transition', [
            'message' => $e->getMessage(),
        ]), 422);
    }
}
