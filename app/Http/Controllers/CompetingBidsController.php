<?php

namespace App\Http\Controllers;

use App\Models\TenantAgentAuction;
use App\Services\CompetingBidsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompetingBidsController extends Controller
{
    protected $competingBidsService;

    public function __construct(CompetingBidsService $competingBidsService)
    {
        $this->competingBidsService = $competingBidsService;
    }

    public function viewCompetingBids($auctionId)
    {
        $user = Auth::user();
        $auction = TenantAgentAuction::findOrFail($auctionId);
        
        if (!$auction->isBiddingPeriodType()) {
            return back()->with('error', 'Competing bids are only visible for Bidding Period listings.');
        }

        if (!$this->competingBidsService->canViewCompetingBids($auctionId, $user->id)) {
            return back()->with('error', 'You must submit a bid to view competing bids.');
        }

        $competingBids = $this->competingBidsService->getCompetingBids($auctionId, $user->id);

        return view('tenant_agent.competing_bids', [
            'auction' => $auction,
            'competingBids' => $competingBids,
            'listingId' => $auction->listing_id ?? ('TAA-' . $auction->id),
        ]);
    }

    public function getCompetingBidsData($auctionId)
    {
        $user = Auth::user();

        if (!$this->competingBidsService->canViewCompetingBids($auctionId, $user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $competingBids = $this->competingBidsService->getCompetingBids($auctionId, $user->id);

        return response()->json([
            'success' => true,
            'competing_bids' => $competingBids,
        ]);
    }
}
