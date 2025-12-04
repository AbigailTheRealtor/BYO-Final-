<?php

namespace App\Http\Controllers;

use App\Http\Livewire\TenantAgentAuction;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TenantAgentAuctionBid;
use App\Models\LandlordAgentAuctionBid;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\UserAgent;

class AgentController extends Controller
{




    public function tenant_list(Request $request)
    {
        $page_data['title'] = 'Tenant\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        // Get all auctions where user has bid
        $auctionIds = TenantAgentAuctionBid::where('user_id', Auth::user()->id)
            ->pluck('tenant_agent_auction_id')
            ->unique()
            ->values()
            ->all();

        // If no bids, return empty
        if (empty($auctionIds)) {
            $page_data['pendingApprovalCount'] = 0;
            $page_data['liveCount'] = 0;
            $page_data['soldCount'] = 0;
            $page_data['auctions'] = collect();
            return view('agent_biding_listing.tenant', $page_data);
        }

        // Manually build the query
        $baseQuery = \App\Models\TenantAgentAuction::where('id', $auctionIds[0]);

        for ($i = 1; $i < count($auctionIds); $i++) {
            $baseQuery->orWhere('id', $auctionIds[$i]);
        }

        // Create status-specific queries
        $pendingQuery = (clone $baseQuery)
            ->where('is_approved', 0)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $liveQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $soldQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 1)
            ->where('is_draft', 0);

        // Get data based on type
        if ($type == "1") {
            $auctions = $pendingQuery->get();
        } else if ($type == "2") {
            $auctions = $liveQuery->get();
        } else if ($type == '3') {
            $auctions = $soldQuery->get();
        } else {
            $auctions = $liveQuery->get();
        }

        $page_data['pendingApprovalCount'] = $pendingQuery->count();
        $page_data['liveCount'] = $liveQuery->count();
        $page_data['soldCount'] = $soldQuery->count();

        $page_data['auctions'] = $auctions;

        return view('agent_biding_listing.tenant', $page_data);
    }

    // public function landlord_list(Request $request)
    // {

    //     $page_data['title'] = 'Landlord\'s Agent Auctions';
    //     $page_data['type'] = $type = $request->type ?? "2";

    //     // Get all auctions where user has bid
    //     $auctionIds = LandlordAgentAuctionBid::where('user_id', Auth::user()->id)
    //         ->pluck('landlord_agent_auction_id')
    //         ->unique()
    //         ->values()
    //         ->all();

    //     // If no bids, return empty
    //     if (empty($auctionIds)) {
    //         $page_data['pendingApprovalCount'] = 0;
    //         $page_data['liveCount'] = 0;
    //         $page_data['soldCount'] = 0;
    //         $page_data['auctions'] = collect();
    //         return view('agent_biding_listing.landlord', $page_data);
    //     }

    //     // Manually build the query
    //     $baseQuery = \App\Models\LandlordAgentAuction::where('id', $auctionIds[0]);

    //     for ($i = 1; $i < count($auctionIds); $i++) {
    //         $baseQuery->orWhere('id', $auctionIds[$i]);
    //     }

    //     // Create status-specific queries
    //     $pendingQuery = (clone $baseQuery)
    //         ->where('is_approved', 0)
    //         ->where('is_sold', 0)
    //         ->where('is_draft', 0);

    //     $liveQuery = (clone $baseQuery)
    //         ->where('is_approved', 1)
    //         ->where('is_sold', 0)
    //         ->where('is_draft', 0);

    //     $soldQuery = (clone $baseQuery)
    //         ->where('is_approved', 1)
    //         ->where('is_sold', 1)
    //         ->where('is_draft', 0);

    //     // Get data based on type
    //     if ($type == "1") {
    //         $auctions = $pendingQuery->get();
    //     } else if ($type == "2") {
    //         $auctions = $liveQuery->get();
    //     } else if ($type == '3') {
    //         $auctions = $soldQuery->get();
    //     } else {
    //         $auctions = $liveQuery->get();
    //     }

    //     $page_data['pendingApprovalCount'] = $pendingQuery->count();
    //     $page_data['liveCount'] = $liveQuery->count();
    //     $page_data['soldCount'] = $soldQuery->count();

    //     $page_data['auctions'] = $auctions;

    //     return view('agent_biding_listing.landlord', $page_data);
    // }






    // public function landlord_list(Request $request)
    // {
    //     $page_data['title'] = "Landlord's Agent Auctions";
    //     $page_data['type'] = "bidding";
    //     $status = $request->status ?? "2";

    //     // 1. Auctions where agent placed bids
    //     $auctionIdsFromBids = LandlordAgentAuctionBid::where('user_id', Auth::id())
    //         ->pluck('landlord_agent_auction_id')
    //         ->unique()
    //         ->values()
    //         ->all();

    //     // 2. Agent assigned properties
    //     $propertyIds = UserAgent::where('agent_id', Auth::id())
    //         ->where('type', 'landlord')
    //         ->pluck('property_id')
    //         ->unique()
    //         ->values()
    //         ->all();

    //     // 3. Awarded (status = 3)
    //     if ($status == "3") {

    //         $baseQuery = LandlordAgentAuction::whereIn('id', $propertyIds)
    //             ->where('is_approved', 1)
    //             ->where('is_sold', 1)
    //             ->where('is_draft', 0);

    //         $page_data['soldCount'] = $baseQuery->count();      // Correct count based on id
    //         $page_data['liveCount'] = 0;
    //         $page_data['pendingApprovalCount'] = 0;

    //         $page_data['auctions'] = $baseQuery->paginate(10);
    //         $page_data['status'] = $status;

    //         return view('agent_biding_listing.landlord', $page_data);
    //     }

    //     // 4. For pending/live → based on bids
    //     if (empty($auctionIdsFromBids)) {

    //         $page_data['pendingApprovalCount'] = 0;
    //         $page_data['liveCount']            = 0;
    //         $page_data['soldCount']            = 0;
    //         $page_data['auctions']             = collect();
    //         $page_data['status']               = $status;

    //         return view('agent_biding_listing.landlord', $page_data);
    //     }

    //     $baseQuery = LandlordAgentAuction::whereIn('id', $auctionIdsFromBids)
    //         ->where('is_draft', 0);

    //     // Counts
    //     $page_data['pendingApprovalCount'] = (clone $baseQuery)->where('is_approved', 0)->where('is_sold', 0)->count();
    //     $page_data['liveCount']            = (clone $baseQuery)->where('is_approved', 1)->where('is_sold', 0)->count();

    //     // SOLD count should match only awarded (property related)
    //     $page_data['soldCount'] = LandlordAgentAuction::whereIn('id', $propertyIds)
    //         ->where('is_approved', 1)
    //         ->where('is_sold', 1)
    //         ->where('is_draft', 0)
    //         ->count();

    //     // Filter results for pending/live
    //     if ($status == "1") {
    //         $auctions = (clone $baseQuery)->where('is_approved', 0)->where('is_sold', 0)->paginate(10);
    //     } elseif ($status == "2") {
    //         $auctions = (clone $baseQuery)->where('is_approved', 1)->where('is_sold', 0)->paginate(10);
    //     } else {
    //         $auctions = (clone $baseQuery)->paginate(10);
    //     }

    //     $page_data['auctions'] = $auctions;
    //     $page_data['status'] = $status;

    //     return view('agent_biding_listing.landlord', $page_data);
    // }



    public function landlord_list(Request $request)
    {
        $page_data['title'] = "Landlord's Agent Auctions";
        $page_data['type'] = "bidding";
        $status = $request->status ?? "2";

        $userId = Auth::id();

        // 1. Auctions where agent placed bids
        $auctionIdsFromBids = LandlordAgentAuctionBid::where('user_id', $userId)
            ->pluck('landlord_agent_auction_id')
            ->unique()
            ->values()
            ->all();

        // 2. Agent assigned properties (awarded / won)
        $propertyIds = UserAgent::where('agent_id', $userId)
            ->where('type', 'landlord')
            ->pluck('property_id') // <-- property_id, not id
            ->unique()
            ->values()
            ->all();

        // 3. Calculate Not Won count
        $notWonCount = LandlordAgentAuction::whereIn('id', $auctionIdsFromBids)
            ->where('is_approved', 1)
            ->where('is_sold', 1)
            ->whereNotIn('id', $propertyIds)
            ->where('is_draft', 0)
            ->count();

        // 4. Calculate Sold / Awarded count
        $soldCount = LandlordAgentAuction::whereIn('id', $propertyIds)
            ->where('is_approved', 1)
            ->where('is_sold', 1)
            ->where('is_draft', 0)
            ->count();

        // 5. Live / pending counts (based on bids)
        $baseQuery = LandlordAgentAuction::whereIn('id', $auctionIdsFromBids)
            ->where('is_draft', 0);

        $pendingApprovalCount = (clone $baseQuery)->where('is_approved', 0)->where('is_sold', 0)->count();
        $liveCount            = (clone $baseQuery)->where('is_approved', 1)->where('is_sold', 0)->count();

        // 6. Select auctions to display based on $status
        if ($status == "1") {
            $auctions = LandlordAgentAuction::whereIn('id', $auctionIdsFromBids)
                ->where('is_approved', 1)
                ->where('is_sold', 1)
                ->whereNotIn('id', $propertyIds)
                ->where('is_draft', 0)
                ->paginate(10);
        } elseif ($status == "3") {
            $auctions = LandlordAgentAuction::whereIn('id', $propertyIds)
                ->where('is_approved', 1)
                ->where('is_sold', 1)
                ->where('is_draft', 0)
                ->paginate(10);
        } else { // Live / status 2
            $auctions = (clone $baseQuery)->where('is_approved', 1)->where('is_sold', 0)->paginate(10);
        }

        $page_data['pendingApprovalCount'] = $pendingApprovalCount;
        $page_data['liveCount']            = $liveCount;
        $page_data['soldCount']            = $soldCount;
        $page_data['notWonCount']          = $notWonCount;
        $page_data['auctions']             = $auctions;
        $page_data['status']               = $status;

        return view('agent_biding_listing.landlord', $page_data);
    }



    public function buyer_list(Request $request)
    {

        $page_data['title'] = 'Buyer\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        // Get all auctions where user has bid
        $auctionIds = BuyerAgentAuctionBid::where('user_id', Auth::user()->id)
            ->pluck('buyer_agent_auction_id')
            ->unique()
            ->values()
            ->all();


        // If no bids, return empty
        if (empty($auctionIds)) {
            $page_data['pendingApprovalCount'] = 0;
            $page_data['liveCount'] = 0;
            $page_data['soldCount'] = 0;
            $page_data['auctions'] = collect();
            return view('agent_biding_listing.buyer', $page_data);
        }

        // Manually build the query
        $baseQuery = \App\Models\BuyerAgentAuction::where('id', $auctionIds[0]);

        for ($i = 1; $i < count($auctionIds); $i++) {
            $baseQuery->orWhere('id', $auctionIds[$i]);
        }

        // Create status-specific queries
        $pendingQuery = (clone $baseQuery)
            ->where('is_approved', 0)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $liveQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $soldQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 1)
            ->where('is_draft', 0);

        // Get data based on type
        if ($type == "1") {
            $auctions = $pendingQuery->get();
        } else if ($type == "2") {
            $auctions = $liveQuery->get();
        } else if ($type == '3') {
            $auctions = $soldQuery->get();
        } else {
            $auctions = $liveQuery->get();
        }

        $page_data['pendingApprovalCount'] = $pendingQuery->count();
        $page_data['liveCount'] = $liveQuery->count();
        $page_data['soldCount'] = $soldQuery->count();

        $page_data['auctions'] = $auctions;

        return view('agent_biding_listing.buyer', $page_data);
    }
    public function seller_list(Request $request)
    {

        $page_data['title'] = 'Seller\'s Agent Auctions';
        $page_data['type'] = $type = $request->type ?? "2";

        // Get all auctions where user has bid
        $auctionIds = SellerAgentAuctionBid::where('user_id', Auth::user()->id)
            ->pluck('seller_agent_auction_id')
            ->unique()
            ->values()
            ->all();


        // If no bids, return empty
        if (empty($auctionIds)) {
            $page_data['pendingApprovalCount'] = 0;
            $page_data['liveCount'] = 0;
            $page_data['soldCount'] = 0;
            $page_data['auctions'] = collect();
            return view('agent_biding_listing.seller', $page_data);
        }

        // Manually build the query
        $baseQuery = \App\Models\SellerAgentAuction::where('id', $auctionIds[0]);

        for ($i = 1; $i < count($auctionIds); $i++) {
            $baseQuery->orWhere('id', $auctionIds[$i]);
        }

        // Create status-specific queries
        $pendingQuery = (clone $baseQuery)
            ->where('is_approved', 0)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $liveQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 0)
            ->where('is_draft', 0);

        $soldQuery = (clone $baseQuery)
            ->where('is_approved', 1)
            ->where('is_sold', 1)
            ->where('is_draft', 0);

        // Get data based on type
        if ($type == "1") {
            $auctions = $pendingQuery->get();
        } else if ($type == "2") {
            $auctions = $liveQuery->get();
        } else if ($type == '3') {
            $auctions = $soldQuery->get();
        } else {
            $auctions = $liveQuery->get();
        }

        $page_data['pendingApprovalCount'] = $pendingQuery->count();
        $page_data['liveCount'] = $liveQuery->count();
        $page_data['soldCount'] = $soldQuery->count();

        $page_data['auctions'] = $auctions;

        return view('agent_biding_listing.seller', $page_data);
    }
}
