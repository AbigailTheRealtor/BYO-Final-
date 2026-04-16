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
use App\Models\TenantAgentAuction as TenantAgentAuctionModel;
use App\Models\BuyerAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\OfferAuction as OfferAuctionModel;
use Carbon\Carbon;

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

        // Create status-specific queries (is_approved and is_sold are varchar columns storing 'true'/'false')
        $pendingQuery = (clone $baseQuery)
            ->where('is_approved', 'false')
            ->where('is_sold', 'false')
            ->where('is_draft', false);

        $liveQuery = (clone $baseQuery)
            ->where('is_approved', 'true')
            ->where('is_sold', 'false')
            ->where('is_draft', false);

        $soldQuery = (clone $baseQuery)
            ->where('is_approved', 'true')
            ->where('is_sold', 'true')
            ->where('is_draft', false);

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

    public function hireListings(Request $request)
    {
        $uid    = Auth::id();
        $filter = $request->get('filter', 'all');

        $tenant   = TenantAgentAuctionModel::where('user_id', $uid)->with('bids')->get()
                        ->map(fn($a) => $this->normalizeHireListing($a, 'tenant'));
        $landlord = LandlordAgentAuction::where('user_id', $uid)->with('bids')->get()
                        ->map(fn($a) => $this->normalizeHireListing($a, 'landlord'));
        $buyer    = BuyerAgentAuction::where('user_id', $uid)->with('bids')->get()
                        ->map(fn($a) => $this->normalizeHireListing($a, 'buyer'));
        $seller   = SellerAgentAuction::where('user_id', $uid)->with('bids')->get()
                        ->map(fn($a) => $this->normalizeHireListing($a, 'seller'));

        $all = collect([...$tenant, ...$landlord, ...$buyer, ...$seller])
                    ->sortByDesc('created_at')
                    ->values();

        $counts = [
            'all'     => $all->count(),
            'active'  => $all->filter(fn($l) => !$l['_draft'] && $l['_approved'] && !$l['_sold'] && !$l['_expired'])->count(),
            'pending' => $all->filter(fn($l) => !$l['_draft'] && !$l['_approved'] && !$l['_sold'])->count(),
            'draft'   => $all->filter(fn($l) => $l['_draft'])->count(),
            'hired'   => $all->filter(fn($l) => $l['_sold'])->count(),
            'expired' => $all->filter(fn($l) => $l['_expired'] && !$l['_sold'] && !$l['_draft'])->count(),
        ];

        $listings = match ($filter) {
            'active'  => $all->filter(fn($l) => !$l['_draft'] && $l['_approved'] && !$l['_sold'] && !$l['_expired']),
            'pending' => $all->filter(fn($l) => !$l['_draft'] && !$l['_approved'] && !$l['_sold']),
            'draft'   => $all->filter(fn($l) => $l['_draft']),
            'hired'   => $all->filter(fn($l) => $l['_sold']),
            'expired' => $all->filter(fn($l) => $l['_expired'] && !$l['_sold'] && !$l['_draft']),
            default   => $all,
        };

        return view('agent.hire-listings', compact('listings', 'filter', 'counts'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // OFFER LISTINGS HUB (Phase 6)
    // ─────────────────────────────────────────────────────────────────────

    public function offerListings(Request $request)
    {
        $uid    = Auth::id();
        $filter = $request->get('filter', 'all');

        $all = OfferAuctionModel::where('user_id', $uid)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => $this->normalizeOfferListing($a));

        $counts = [
            'all'      => $all->count(),
            'active'   => $all->filter(fn($l) => !$l['_draft'] && $l['_approved'] && !$l['_sold'] && !$l['_expired'])->count(),
            'pending'  => $all->filter(fn($l) => !$l['_draft'] && !$l['_approved'] && !$l['_sold'])->count(),
            'draft'    => $all->filter(fn($l) => $l['_draft'])->count(),
            'accepted' => $all->filter(fn($l) => $l['_sold'])->count(),
            'expired'  => $all->filter(fn($l) => $l['_expired'] && !$l['_sold'] && !$l['_draft'])->count(),
        ];

        $listings = match ($filter) {
            'active'   => $all->filter(fn($l) => !$l['_draft'] && $l['_approved'] && !$l['_sold'] && !$l['_expired']),
            'pending'  => $all->filter(fn($l) => !$l['_draft'] && !$l['_approved'] && !$l['_sold']),
            'draft'    => $all->filter(fn($l) => $l['_draft']),
            'accepted' => $all->filter(fn($l) => $l['_sold']),
            'expired'  => $all->filter(fn($l) => $l['_expired'] && !$l['_sold'] && !$l['_draft']),
            default    => $all,
        };

        return view('agent.offer-listings', compact('listings', 'filter', 'counts'));
    }

    private function normalizeOfferListing(OfferAuctionModel $auction): array
    {
        $isDraft    = (bool) $auction->is_draft;
        $isApproved = (bool) $auction->is_approved;
        $isSold     = (bool) $auction->is_sold;

        $expiryRaw  = $auction->get->listing_expiration ?? null;
        $isExpired  = $expiryRaw && Carbon::now()->gt(Carbon::parse($expiryRaw));

        if ($isDraft) {
            $statusLabel = 'Draft';
            $statusClass = 'secondary';
        } elseif ($isSold) {
            $statusLabel = 'Accepted';
            $statusClass = 'success';
        } elseif (!$isApproved) {
            $statusLabel = 'Pending Approval';
            $statusClass = 'warning';
        } elseif ($isExpired) {
            $statusLabel = 'Expired';
            $statusClass = 'danger';
        } else {
            $statusLabel = $auction->get->listing_status ?? 'Active';
            $statusClass = 'primary';
        }

        $offerType = $auction->get->offer_type ?? '';
        $address   = $auction->get->property_address ?? '';
        $state     = $auction->get->state ?? '';
        $title     = $auction->title ?? $auction->get->listing_title ?? ($address ?: 'Offer Listing #' . $auction->id);

        $draftRoute = $isDraft
            ? route('offer.listing.draft', $auction->id)
            : null;

        $editRoute  = route('offer.listing.draft', $auction->id);

        return [
            'id'           => $auction->id,
            'listing_id'   => $auction->listing_id ?? 'OFA-' . $auction->id,
            'title'        => $title,
            'address'      => $address,
            'state'        => $state,
            'offer_type'   => $offerType,
            'offer_price'  => $auction->get->offer_price  ?? null,
            'monthly_rent' => $auction->get->monthly_rent ?? null,
            'closing_date' => $auction->get->closing_date ?? null,
            'expiry'       => $expiryRaw,
            'created_at'   => $auction->created_at,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'draft_route'  => $draftRoute,
            'edit_route'   => $editRoute,
            '_draft'       => $isDraft,
            '_approved'    => $isApproved,
            '_sold'        => $isSold,
            '_expired'     => $isExpired,
        ];
    }

    private function normalizeHireListing($auction, string $role): array
    {
        $isDraft    = (bool) $auction->is_draft;
        $isApproved = in_array($auction->is_approved, [true, 1, '1', 'true'], true);
        $isSold     = in_array($auction->is_sold,     [true, 1, '1', 'true'], true);

        $expiryRaw  = $auction->get->expiration_date ?? null;
        $isExpired  = $expiryRaw && Carbon::now()->gt(Carbon::parse($expiryRaw));

        $auctionType = strtolower(trim($auction->get->auction_type ?? ''));

        if ($isDraft) {
            $statusLabel = 'Draft';
            $statusClass = 'secondary';
        } elseif ($isSold) {
            $statusLabel = 'Hired Agent';
            $statusClass = 'success';
        } elseif (!$isApproved) {
            $statusLabel = 'Pending Approval';
            $statusClass = 'warning';
        } elseif ($isExpired) {
            $statusLabel = 'Expired';
            $statusClass = 'danger';
        } else {
            $statusLabel = 'Active';
            $statusClass = 'primary';
        }

        $displayRole = match ($role) {
            'landlord' => "Listing Owner",
            'tenant'   => "Tenant's Agent",
            'buyer'    => "Buyer's Agent",
            'seller'   => "Seller's Agent",
        };

        $editRoute = match ($role) {
            'seller'   => route('editSellerAgentHireAuction', $auction->id),
            'landlord' => route('landlord.hire.agent.auction.edit', $auction->id),
            default    => route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => $role]),
        };

        $viewRoute = match ($role) {
            'tenant'   => route('tenant.agent.view.auction.view', $auction->id),
            'landlord' => route('landlord.agent.auction.view', $auction->id),
            'buyer'    => route('buyer.view-auction', $auction->id),
            'seller'   => route('seller.agent.auction.detail', $auction->id),
        };

        $draftRoute = $isDraft
            ? route('hire.agent.auction.draft', ['user_type' => $role, 'listingId' => $auction->id])
            : null;

        $createRoute = route('hire.agent.auction', ['user_type' => $role]);

        $address = $auction->get->address ?? $auction->address ?? '';
        $state   = $auction->get->state ?? '';
        $title   = $auction->title ?? $auction->get->listing_title ?? ($address ?: 'Listing #' . $auction->id);

        return [
            'id'           => $auction->id,
            'listing_id'   => $auction->listing_id ?? strtoupper(substr($role, 0, 1)) . 'AA-' . $auction->id,
            'role'         => $role,
            'display_role' => $displayRole,
            'title'        => $title,
            'address'      => $address,
            'state'        => $state,
            'auction_type' => $auctionType,
            'expiry'       => $expiryRaw,
            'bid_count'    => $auction->bids->count(),
            'created_at'   => $auction->created_at,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'view_route'   => $viewRoute,
            'edit_route'   => $editRoute,
            'draft_route'   => $draftRoute,
            'create_route'  => $createRoute,
            'referral_pct'  => $auction->get->referral_percentage ?? null,
            'workflow_type' => $auction->get->workflow_type ?? 'hire_agent',
            '_draft'        => $isDraft,
            '_approved'    => $isApproved,
            '_sold'        => $isSold,
            '_expired'     => $isExpired,
        ];
    }
}
