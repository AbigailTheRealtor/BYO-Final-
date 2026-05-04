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

    public function offerListingView(int $id)
    {
        $uid = Auth::id();

        // Each role uses its own auto-increment table, so the same $id can appear
        // in more than one table. We search in a fixed priority order and return
        // the first match owned by the current user. Callers that need a specific
        // role should pass a role discriminator (e.g. via query param or session)
        // and extend this method once the route is made role-aware.
        $roleMap = [
            'seller'   => SellerAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            'buyer'    => BuyerAgentAuction::class,
            'tenant'   => TenantAgentAuctionModel::class,
        ];

        $auction = null;
        $role    = null;

        // Prefer an explicit role discriminator passed via query string to avoid
        // cross-table ID collisions (each role table uses its own auto-increment).
        $requestedRole = request()->query('role');
        if ($requestedRole && isset($roleMap[$requestedRole])) {
            $modelClass = $roleMap[$requestedRole];
            $found = $modelClass::where('id', $id)
                ->where('user_id', $uid)
                ->with('meta')
                ->first();
            if ($found) {
                $auction = $found;
                $role    = $requestedRole;
            }
        }

        // Fall back to priority-order search when no role discriminator provided.
        if (!$auction) {
            foreach ($roleMap as $r => $modelClass) {
                $found = $modelClass::where('id', $id)
                    ->where('user_id', $uid)
                    ->with('meta')
                    ->first();
                if ($found) {
                    $auction = $found;
                    $role    = $r;
                    break;
                }
            }
        }

        if (!$auction) {
            abort(404);
        }

        $meta = $auction->meta->pluck('meta_value', 'meta_key');

        $isDraft    = (bool) $auction->is_draft;
        $isApproved = (bool) $auction->is_approved;
        $isSold     = (bool) $auction->is_sold;
        $expiryRaw  = $meta['listing_expiration'] ?? null;
        $isExpired  = $expiryRaw && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiryRaw));

        if ($isDraft) {
            $statusLabel = 'Draft';        $statusClass = 'secondary';
        } elseif ($isSold) {
            $statusLabel = 'Accepted';     $statusClass = 'success';
        } elseif (!$isApproved) {
            $statusLabel = 'Pending Review'; $statusClass = 'warning';
        } elseif ($isExpired) {
            $statusLabel = 'Expired';      $statusClass = 'danger';
        } else {
            $statusLabel = $meta['listing_status'] ?? 'Active'; $statusClass = 'primary';
        }

        $editRoute = match ($role) {
            'seller'   => route('offer.listing.seller.edit', ['auctionId' => $auction->id]),
            'landlord' => route('offer.listing.landlord.edit', ['auctionId' => $auction->id]),
            'buyer'    => route('offer.listing.buyer.edit', ['auctionId' => $auction->id]),
            'tenant'   => route('offer.listing.tenant.edit', ['auctionId' => $auction->id]),
        };

        // Parse JSON-encoded array fields for display
        $parseJsonMeta = function(string $key) use ($meta): array {
            $raw = $meta[$key] ?? '';
            if (!$raw) return [];
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_filter($decoded, fn($v) => $v !== null && $v !== '') : [];
        };

        // Property photos stored as JSON array of paths or a single path string
        $propertyPhotosRaw = $meta['property_photos'] ?? '';
        $propertyPhotos = [];
        if ($propertyPhotosRaw) {
            $decoded = json_decode($propertyPhotosRaw, true);
            if (is_array($decoded)) {
                $propertyPhotos = array_filter($decoded);
            } elseif (is_string($propertyPhotosRaw) && $propertyPhotosRaw !== '') {
                $propertyPhotos = [$propertyPhotosRaw];
            }
        }

        $data = [
            // ── Identity & status ─────────────────────────────────────────
            'id'           => $auction->id,
            'role'         => $role,
            'listing_id'   => $auction->listing_id ?? ('OFA-' . $auction->id),
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'edit_route'   => $editRoute,
            'hub_route'    => route('agent.offer-listings'),

            // ── Listing Details ───────────────────────────────────────────
            'title'                 => $auction->title ?? $meta['listing_title'] ?? '',
            'listing_title'         => $meta['listing_title']          ?? '',
            'service_type'          => $meta['service_type']           ?? '',
            'auction_type'          => $meta['auction_type']           ?? '',
            'offer_type'            => $meta['offer_type']             ?? '',
            'listing_status'        => $meta['listing_status']         ?? '',
            'listing_date'          => $meta['listing_date']           ?? '',
            'desired_agent_hire_date' => $meta['desired_agent_hire_date'] ?? '',
            'expiration_date'       => $meta['expiration_date']        ?? $expiryRaw,
            'listing_expiration'    => $expiryRaw,
            'auction_time'          => $meta['auction_time']           ?? '',
            'working_with_agent'    => $meta['working_with_agent']     ?? '',
            'meeting_preference'    => $meta['meeting_Preference']     ?? $meta['meeting_preference'] ?? '',
            'agent_bid_visibility'  => $meta['agent_bid_visibility']   ?? '',

            // ── Location ──────────────────────────────────────────────────
            'property_address' => $meta['address'] ?? $meta['property_address'] ?? '',
            'property_city'    => $meta['property_city']   ?? '',
            'property_county'  => $meta['property_county'] ?? '',
            'property_state'   => $meta['property_state']  ?? $meta['state'] ?? '',
            'property_zip'     => $meta['property_zip']    ?? $meta['zip_code'] ?? '',
            'state'            => $meta['state']            ?? '',
            'cities'           => $parseJsonMeta('cities'),
            'counties'         => $parseJsonMeta('counties'),
            'zip_codes'        => $parseJsonMeta('zipCodes'),

            // ── Property Details ──────────────────────────────────────────
            'property_type'          => $meta['property_type']          ?? '',
            'property_items'         => $parseJsonMeta('property_items'),
            'other_property_items'   => $meta['other_property_items']   ?? '',
            'condition_prop'         => $meta['condition_prop']         ?? '',
            'condition_prop_buyer'   => $parseJsonMeta('condition_prop_buyer'),
            'other_property_condition' => $meta['other_property_condition'] ?? '',
            'bedrooms'               => $meta['bedrooms']               ?? '',
            'other_bedrooms'         => $meta['other_bedrooms']         ?? '',
            'bathrooms'              => $meta['bathrooms']              ?? '',
            'other_bathrooms'        => $meta['other_bathrooms']        ?? '',
            'minimum_heated_square'  => $meta['minimum_heated_square']  ?? '',
            'total_square_feet'      => $meta['total_square_feet']      ?? '',
            'sqft_heated_source'     => $meta['sqft_heated_source']     ?? '',
            'minimum_leaseable'      => $meta['minimum_leaseable']      ?? '',
            'leasing_space'          => $meta['leasing_space']          ?? '',
            'leasing_space_property' => $meta['leasing_space_property'] ?? '',
            'leasing_spaces'         => $meta['leasing_spaces']         ?? '',
            'leasing_spaces_tenant'  => $parseJsonMeta('leasing_spaces_tenant'),
            'min_acreage'            => $meta['min_acreage']            ?? '',
            'total_acreage'          => $meta['total_acreage']          ?? '',
            'lot_dimensions'         => $meta['lot_dimensions']         ?? '',
            'front_footage'          => $meta['front_footage']          ?? '',
            'number_of_wells'        => $meta['number_of_wells']        ?? '',
            'number_of_septics'      => $meta['number_of_septics']      ?? '',
            'leasing_55_plus'        => $meta['leasing_55_plus']        ?? '',
            'tenant_require'         => $parseJsonMeta('tenant_require'),
            'garage_needed'          => $meta['garage_needed']          ?? '',
            'garage_parking_spaces'  => $meta['garage_parking_spaces']  ?? '',
            'carport_needed'         => $meta['carport_needed']         ?? '',
            'pool_needed'            => $meta['pool_needed']            ?? '',
            'pool_type'              => $parseJsonMeta('pool_type'),
            'view_preference'        => $parseJsonMeta('view_preference'),
            'other_preferences'      => $meta['other_preferences']      ?? '',
            'appliances'             => $parseJsonMeta('appliances'),
            'other_appliances'       => $meta['other_appliances']       ?? '',
            'real_estate_purchase'   => $meta['real_estate_purchase']   ?? '',
            'number_of_unit'         => $meta['number_of_unit']         ?? '',
            'number_of_units'        => $meta['number_of_units']        ?? '',
            'unit_number'            => $meta['unit_number']            ?? '',
            'unit_buildings'         => $meta['unit_buildings']         ?? '',
            'unit_size'              => $meta['unit_size']              ?? '',
            'property_criteria'      => $meta['property_criteria']      ?? '',
            'preferance_details'     => $meta['preferance_details']     ?? '',
            'year_built'             => $meta['year_built']             ?? '',
            'zoning'                 => $meta['zoning']                 ?? '',
            'roof_type'              => $parseJsonMeta('roof_type'),
            'exterior_construction'  => $parseJsonMeta('exterior_construction'),
            'foundation'             => $parseJsonMeta('foundation'),
            'heating_and_fuel'       => $parseJsonMeta('heating_and_fuel'),
            'air_conditioning'       => $parseJsonMeta('air_conditioning'),
            'water'                  => $parseJsonMeta('water'),
            'sewer'                  => $parseJsonMeta('sewer'),
            'utilities'              => $meta['utilities'] ?? '',
            'road_frontage'          => $parseJsonMeta('road_frontage'),
            'road_surface_type'      => $parseJsonMeta('road_surface_type'),
            'electrical_service'     => $parseJsonMeta('electrical_service'),
            'ceiling_height'         => $meta['ceiling_height']         ?? '',
            'building_features'      => $parseJsonMeta('building_features'),
            'number_water_meters'    => $meta['number_water_meters']    ?? '',
            'number_electric_meters' => $meta['number_electric_meters'] ?? '',
            'current_use'            => $parseJsonMeta('current_use'),
            'current_adjacent_use'   => $parseJsonMeta('current_adjacent_use'),
            'fences'                 => $parseJsonMeta('fences'),
            'vegetation'             => $parseJsonMeta('vegetation'),
            'buildable'              => $meta['buildable']              ?? '',
            'easements'              => $parseJsonMeta('easements'),
            'sale_includes'          => $parseJsonMeta('sale_includes'),
            'licenses'               => $parseJsonMeta('licenses'),
            'business_name'          => $meta['business_name']          ?? '',
            'year_established'       => $meta['year_established']       ?? '',
            'non_negotiable_amenities' => $parseJsonMeta('non_negotiable_amenities'),
            'other_non_negotiable_amenities' => $meta['other_non_negotiable_amenities'] ?? '',
            'assets'                 => $meta['assets']                 ?? '',
            'assets_other'           => $meta['assets_other']           ?? '',
            'business_assets'        => $parseJsonMeta('business_assets'),
            'additional_details'     => $meta['additional_details']     ?? '',
            'unit_type_configurations' => $parseJsonMeta('unit_type_configurations'),
            'occupant_status'        => $meta['occupant_status']        ?? '',
            'occupant_tenant'        => $meta['occupant_tenant']        ?? '',
            'occupant_types'         => $meta['occupant_types']         ?? '',
            'occupant_types_tenant'  => $meta['occupant_types_tenant']  ?? '',
            'occupied_until'         => $meta['occupied_until']         ?? '',
            'occupancy_status'       => $meta['occupancy_status']       ?? '',

            // ── Sale / Purchase / Lease Terms ─────────────────────────────
            'sale_provision'              => $parseJsonMeta('sale_provision'),
            'sale_provision_other'        => $meta['sale_provision_other']        ?? '',
            'sale_provision_assignment'   => $meta['sale_provision_assignment']   ?? '',
            'assignment_fee_type'         => $meta['assignment_fee_type']         ?? '',
            'assignment_fee_amount'       => $meta['assignment_fee_amount']       ?? '',
            'buyer_sell_contract'         => $meta['buyer_sell_contract']         ?? '',
            'target_closing_date'         => $meta['target_closing_date']         ?? '',
            'maximum_budget'              => $meta['maximum_budget']              ?? '',
            'starting_price'              => $meta['starting_price']              ?? '',
            'reserve_price'               => $meta['reserve_price']               ?? '',
            'buy_now_price'               => $meta['buy_now_price']               ?? '',
            'purchase_price'              => $meta['purchase_price']              ?? '',
            'offered_financing'           => $parseJsonMeta('offered_financing'),
            'other_financing'             => $meta['other_financing']             ?? '',
            'cash_budget'                 => $meta['cash_budget']                 ?? '',
            'pre_approved'                => $meta['pre_approved']                ?? '',
            'pre_approval_amount'         => $meta['pre_approval_amount']         ?? '',
            'down_payment_type'           => $meta['down_payment_type']           ?? '',
            'down_payment_amount'         => $meta['down_payment_amount']         ?? '',
            // Seller financing
            'seller_financing_type'       => $meta['seller_financing_type']       ?? '',
            'seller_financing_amount'     => $meta['seller_financing_amount']     ?? '',
            'interest_rate'               => $meta['interest_rate']               ?? '',
            'loan_duration'               => $meta['loan_duration']               ?? '',
            'prepayment_penalty'          => $meta['prepayment_penalty']          ?? '',
            'prepayment_penalty_amount'   => $meta['prepayment_penalty_amount']   ?? '',
            'balloon_payment_amount'      => $meta['balloon_payment_amount']      ?? '',
            'balloon_payment_date'        => $meta['balloon_payment_date']        ?? '',
            'seller_amortization_type'    => $meta['seller_amortization_type']    ?? '',
            'seller_payment_frequency'    => $meta['seller_payment_frequency']    ?? '',
            'seller_late_fee_amount'      => $meta['seller_late_fee_amount']      ?? '',
            // Assumable
            'assumable_terms'             => $meta['assumable_terms']             ?? '',
            'assumable_loan_type'         => $meta['assumable_loan_type']         ?? '',
            'outstanding_balance'         => $meta['outstanding_balance']         ?? '',
            'max_assumable_rate'          => $meta['max_assumable_rate']          ?? '',
            'assumable_monthly_escrow'    => $meta['assumable_monthly_escrow']    ?? '',
            'assumable_loan_term_remaining' => $meta['assumable_loan_term_remaining'] ?? '',
            'assumable_loan_origination_date' => $meta['assumable_loan_origination_date'] ?? '',
            'assumable_loan_servicer'     => $meta['assumable_loan_servicer']     ?? '',
            'assumable_fee_amount'        => $meta['assumable_fee_amount']        ?? '',
            'assumable_occupancy_requirement' => $meta['assumable_occupancy_requirement'] ?? '',
            'max_monthly_payment'         => $meta['max_monthly_payment']         ?? '',
            'gap_payment_amount'          => $meta['gap_payment_amount']          ?? '',
            // Exchange / Trade
            'exchange_item'               => $parseJsonMeta('exchange_item'),
            'exchange_item_value'         => $meta['exchange_item_value']         ?? '',
            'exchange_item_condition'     => $meta['exchange_item_condition']     ?? '',
            'additional_cash'             => $meta['additional_cash']             ?? '',
            'value_determination'         => $meta['value_determination']         ?? '',
            'exchange_transfer_method'    => $meta['exchange_transfer_method']    ?? '',
            'exchange_liens'              => $meta['exchange_liens']              ?? '',
            'exchange_liens_details'      => $meta['exchange_liens_details']      ?? '',
            'exchange_inspection_rights'  => $meta['exchange_inspection_rights']  ?? '',
            // Lease Option
            'lease_option_price'          => $meta['lease_option_price']          ?? '',
            'lease_option_terms'          => $meta['lease_option_terms']          ?? '',
            'lease_option_duration'       => $meta['lease_option_duration']       ?? '',
            'lease_option_payment'        => $meta['lease_option_payment']        ?? '',
            'lease_option_conditions'     => $meta['lease_option_conditions']     ?? '',
            'has_option_fee'              => $meta['has_option_fee']              ?? '',
            'option_fee_amount'           => $meta['option_fee_amount']           ?? '',
            'seller_lease_option_fee_credit' => $meta['seller_lease_option_fee_credit'] ?? '',
            'lease_option_fee_credit'        => $meta['lease_option_fee_credit']        ?? '',
            'seller_lease_option_maintenance' => $meta['seller_lease_option_maintenance'] ?? '',
            'seller_lease_option_extension_terms' => $meta['seller_lease_option_extension_terms'] ?? '',
            'lease_option_maintenance'    => $meta['lease_option_maintenance']    ?? '',
            'lease_option_extension_terms' => $meta['lease_option_extension_terms'] ?? '',
            'interested_lease_option'     => $meta['interested_lease_option']     ?? '',
            // Lease Purchase
            'lease_purchase_price'        => $meta['lease_purchase_price']        ?? '',
            'lease_purchase_terms'        => $meta['lease_purchase_terms']        ?? '',
            'lease_purchase_duration'     => $meta['lease_purchase_duration']     ?? '',
            'lease_purchase_payment'      => $meta['lease_purchase_payment']      ?? '',
            'lease_purchase_conditions'   => $meta['lease_purchase_conditions']   ?? '',
            'lease_purchase_rent_credit'  => $meta['lease_purchase_rent_credit']  ?? '',
            'lease_purchase_rent_credit_amount' => $meta['lease_purchase_rent_credit_amount'] ?? '',
            'lease_purchase_deposit'      => $meta['lease_purchase_deposit']      ?? '',
            'lease_purchase_maintenance'  => $meta['lease_purchase_maintenance']  ?? '',
            'lease_purchase_extension_terms' => $meta['lease_purchase_extension_terms'] ?? '',
            // Crypto / NFT
            'cryptocurrency_type'         => $meta['cryptocurrency_type']         ?? '',
            'crypto_percentage'           => $meta['crypto_percentage']           ?? '',
            'cash_percentage_crypto'      => $meta['cash_percentage_crypto']      ?? '',
            'crypto_transfer_timing'      => $meta['crypto_transfer_timing']      ?? '',
            'crypto_exchange_method'      => $meta['crypto_exchange_method']      ?? '',
            'nft_description'             => $meta['nft_description']             ?? '',
            'nft_percentage'              => $meta['nft_percentage']              ?? '',
            'cash_percentage_nft'         => $meta['cash_percentage_nft']         ?? '',
            // Seller-specific sale terms
            'initial_deposit_requested'   => $meta['initial_deposit_requested']   ?? '',
            'initial_deposit_timeframe'   => $meta['initial_deposit_timeframe']   ?? '',
            'escrow_agent_preference'     => $meta['escrow_agent_preference']     ?? '',
            'preferred_inspection_period' => $meta['preferred_inspection_period'] ?? '',
            'appraisal_contingency_preference' => $meta['appraisal_contingency_preference'] ?? '',
            'financing_contingency_preference' => $meta['financing_contingency_preference'] ?? '',
            'sale_of_buyer_property_contingency' => $meta['sale_of_buyer_property_contingency'] ?? '',
            'seller_contribution_credit_offered' => $meta['seller_contribution_credit_offered'] ?? '',
            'seller_contribution_amount_details' => $meta['seller_contribution_amount_details'] ?? '',
            'possession_preference'       => $meta['possession_preference']       ?? '',
            'possession_details'          => $meta['possession_details']          ?? '',
            'included_personal_property'  => $meta['included_personal_property']  ?? '',
            'excluded_items'              => $meta['excluded_items']              ?? '',
            'home_warranty_offered'       => $meta['home_warranty_offered']       ?? '',
            'home_warranty_amount_details' => $meta['home_warranty_amount_details'] ?? '',
            'hoa_condo_association_terms' => $meta['hoa_condo_association_terms'] ?? '',
            'additional_seller_sale_terms' => $meta['additional_seller_sale_terms'] ?? '',
            // Buyer-specific purchase terms
            'earnest_money_amount'        => $meta['earnest_money_amount']        ?? '',
            'earnest_money_timing'        => $meta['earnest_money_timing']        ?? '',
            'inspection_period_days'      => $meta['inspection_period_days']      ?? '',
            'inspection_contingency_buyer' => $meta['inspection_contingency_buyer'] ?? '',
            'appraisal_contingency_buyer' => $meta['appraisal_contingency_buyer'] ?? '',
            'financing_contingency_buyer' => $meta['financing_contingency_buyer'] ?? '',
            'financing_contingency_days_buyer' => $meta['financing_contingency_days_buyer'] ?? '',
            'seller_contribution'         => $meta['seller_contribution']         ?? '',
            'seller_contribution_details' => $meta['seller_contribution_details'] ?? '',
            'home_warranty_requested'     => $meta['home_warranty_requested']     ?? '',
            'home_warranty_details'       => $meta['home_warranty_details']       ?? '',
            'as_is_purchase'              => $meta['as_is_purchase']              ?? '',
            'property_inclusions'         => $meta['property_inclusions']         ?? '',
            'property_exclusions'         => $meta['property_exclusions']         ?? '',
            'closing_cost_responsibility' => $meta['closing_cost_responsibility'] ?? '',
            'additional_purchase_terms'   => $meta['additional_purchase_terms']   ?? '',
            // Landlord/Tenant lease terms
            'desired_rental_amount'       => $meta['desired_rental_amount']       ?? '',
            'starting_rent'               => $meta['starting_rent']               ?? '',
            'reserve_rent'                => $meta['reserve_rent']                ?? '',
            'lease_now_price'             => $meta['lease_now_price']             ?? '',
            'lease_amount_frequency'      => $meta['lease_amount_frequency']      ?? '',
            'desired_lease_length'        => $parseJsonMeta('desired_lease_length'),
            'rent_includes'               => $parseJsonMeta('rent_includes'),
            'terms_of_lease'              => $parseJsonMeta('terms_of_lease'),
            'tenant_pays'                 => $parseJsonMeta('tenant_pays'),
            'owner_pays'                  => $parseJsonMeta('owner_pays'),
            'lease_for'                   => $parseJsonMeta('lease_for'),
            'lease_by'                    => $meta['lease_by']                    ?? '',
            'lease_date'                  => $meta['lease_date']                  ?? '',
            'lease_available_date'        => $meta['lease_available_date']        ?? '',
            'security_deposit_required'   => $meta['security_deposit_required']   ?? '',
            'first_month_rent_required'   => $meta['first_month_rent_required']   ?? '',
            'last_month_rent_required'    => $meta['last_month_rent_required']    ?? '',
            'total_move_in_funds_required' => $meta['total_move_in_funds_required'] ?? '',
            'pet_policy'                  => $meta['pet_policy']                  ?? '',
            'pet_deposit_fee_rent'        => $meta['pet_deposit_fee_rent']        ?? '',
            'number_of_occupants_allowed' => $meta['number_of_occupants_allowed'] ?? '',
            'parking_terms'               => $meta['parking_terms']               ?? '',
            'utility_responsibility'      => $meta['utility_responsibility']      ?? '',
            'll_maintenance_responsibility' => $meta['ll_maintenance_responsibility'] ?? '',
            'renewal_option_offered'      => $meta['renewal_option_offered']      ?? '',
            'renewal_option_details'      => $meta['renewal_option_details']      ?? '',
            'landlord_approval_conditions' => $meta['landlord_approval_conditions'] ?? '',
            'additional_landlord_lease_terms' => $meta['additional_landlord_lease_terms'] ?? '',
            'commercial_lease_type'       => $meta['commercial_lease_type']       ?? '',
            // Tenant pre-screening
            'pets'                        => $meta['pets']                        ?? '',
            'number_of_pets'              => $meta['number_of_pets']              ?? '',
            'breed_of_pets'               => $meta['breed_of_pets']               ?? '',
            'type_of_pets'                => $meta['type_of_pets']                ?? '',
            'weight_of_pets'              => $meta['weight_of_pets']              ?? '',
            'has_breed_restrictions'      => $meta['has_breed_restrictions']      ?? '',
            'breed_restrictions'          => $meta['breed_restrictions']          ?? '',
            'service_animal'              => $meta['service_animal']              ?? '',
            'support_animal'              => $meta['support_animal']              ?? '',
            'credit_scroe_rating'         => $parseJsonMeta('credit_scroe_rating'),
            'prior_eviction'              => $meta['prior_eviction']              ?? '',
            'eviction_explanation'        => $meta['eviction_explanation']        ?? '',
            'prior_felony'                => $meta['prior_felony']                ?? '',
            'prior_felony_explanation'    => $meta['prior_felony_explanation']    ?? '',
            'monthly_income'              => $meta['monthly_income']              ?? '',
            'number_occupant'             => $meta['number_occupant']             ?? '',
            'screening_concerns'          => $meta['screening_concerns']          ?? '',
            'screening_concerns_explanation' => $meta['screening_concerns_explanation'] ?? '',
            // Desired rent / move-in (tenant)
            'desired_rent'                => $meta['desired_rent']                ?? '',
            'desired_rental_amount_tenant' => $meta['desired_rental_amount_tenant'] ?? '',
            'budget'                      => $meta['budget']                      ?? '',

            // ── Financial Details (Income / Commercial / Business) ─────────
            'gross_annual_income'         => $meta['gross_annual_income']         ?? '',
            'annual_operating_expenses'   => $meta['annual_operating_expenses']   ?? '',
            'rent_roll_available'         => $meta['rent_roll_available']         ?? '',
            'operating_statement_available' => $meta['operating_statement_available'] ?? '',
            'price_per_sqft'              => $meta['price_per_sqft']              ?? '',
            'existing_lease_type'         => $meta['existing_lease_type']         ?? '',
            'lease_expiration'            => $meta['lease_expiration']            ?? '',
            'lease_assignable'            => $meta['lease_assignable']            ?? '',
            'annual_revenue'              => $meta['annual_revenue']              ?? '',
            'gross_profit'               => $meta['gross_profit']               ?? '',
            'sde_ebitda'                  => $meta['sde_ebitda']                  ?? '',
            'inventory_value'             => $meta['inventory_value']             ?? '',
            'ffe_value'                   => $meta['ffe_value']                   ?? '',
            'reason_for_sale'             => $meta['reason_for_sale']             ?? '',
            'employee_count'              => $meta['employee_count']              ?? '',
            'financial_statements_available' => $meta['financial_statements_available'] ?? '',
            'tax_returns_available'       => $meta['tax_returns_available']       ?? '',
            'nda_required'                => $meta['nda_required']                ?? '',
            'minimum_annual_net_income'   => $meta['minimum_annual_net_income']   ?? '',
            'minimum_cap_rate'            => $meta['minimum_cap_rate']            ?? '',

            // ── Tax, Legal, HOA & Disclosures (Seller) ────────────────────
            'parcel_id'                   => $meta['parcel_id']                   ?? '',
            'tax_year'                    => $meta['tax_year']                    ?? '',
            'annual_property_taxes'       => $meta['annual_property_taxes']       ?? '',
            'legal_description'           => $meta['legal_description']           ?? '',
            'flood_zone_code'             => $meta['flood_zone_code']             ?? '',
            'flood_insurance_required'    => $meta['flood_insurance_required']    ?? '',
            'flood_zone_panel'            => $meta['flood_zone_panel']            ?? '',
            'has_cdd'                     => $meta['has_cdd']                     ?? '',
            'annual_cdd_fee'              => $meta['annual_cdd_fee']              ?? '',
            'has_special_assessments'     => $meta['has_special_assessments']     ?? '',
            'special_assessment_amount'   => $meta['special_assessment_amount']   ?? '',
            'special_assessment_description' => $meta['special_assessment_description'] ?? '',
            'has_hoa'                     => $meta['has_hoa']                     ?? '',
            'association_type'            => $meta['association_type']            ?? '',
            'association_name'            => $meta['association_name']            ?? '',
            'association_fee_amount'      => $meta['association_fee_amount']      ?? '',
            'association_fee_frequency'   => $meta['association_fee_frequency']   ?? '',
            'association_approval_required' => $meta['association_approval_required'] ?? '',
            'association_approval_process' => $meta['association_approval_process'] ?? '',
            'association_application_fee' => $meta['association_application_fee'] ?? '',
            'association_fee_includes'    => $parseJsonMeta('association_fee_includes'),
            'association_amenities'       => $parseJsonMeta('association_amenities'),
            'leasing_restrictions'        => $meta['leasing_restrictions']        ?? '',
            'min_lease_period'            => $meta['min_lease_period']            ?? '',
            'max_leases_per_year'         => $meta['max_leases_per_year']         ?? '',
            'additional_lease_restrictions' => $meta['additional_lease_restrictions'] ?? '',
            'pet_restrictions'            => $meta['pet_restrictions']            ?? '',
            'pet_restrictions_detail'     => $meta['pet_restrictions_detail']     ?? '',
            // Disclosure flags
            'seller_disclosure_available' => $meta['seller_disclosure_available'] ?? '',
            'survey_available'            => $meta['survey_available']            ?? '',
            'inspection_report_available' => $meta['inspection_report_available'] ?? '',
            'hoa_condo_docs_available'    => $meta['hoa_condo_docs_available']    ?? '',
            'flood_disclosure_available'  => $meta['flood_disclosure_available']  ?? '',
            'lead_based_paint_disclosure' => $meta['lead_based_paint_disclosure'] ?? '',
            'environmental_report_available' => $meta['environmental_report_available'] ?? '',

            // ── Disclosure uploaded file paths (seller) ───────────────────
            'seller_disclosure_file_path'    => $meta['seller_disclosure_file_path']    ?? '',
            'survey_file_path'               => $meta['survey_file_path']               ?? '',
            'inspection_report_file_path'    => $meta['inspection_report_file_path']    ?? '',
            'hoa_condo_docs_file_path'       => $meta['hoa_condo_docs_file_path']       ?? '',
            'flood_disclosure_file_path'     => $meta['flood_disclosure_file_path']     ?? '',
            'lead_based_paint_file_path'     => $meta['lead_based_paint_file_path']     ?? '',
            'environmental_report_file_path' => $meta['environmental_report_file_path'] ?? '',
            'listing_documents'              => $meta['listing_documents']              ?? '',
            'additional_documents'           => $parseJsonMeta('additional_documents'),

            // ── Broker Compensation & Terms ───────────────────────────────
            'commission_structure'         => $meta['commission_structure']         ?? '',
            'services'                     => $parseJsonMeta('services'),
            'flat_fee_services'            => $parseJsonMeta('flat_fee_services'),
            'services_snapshot'            => $parseJsonMeta('services_snapshot'),
            'other_services'               => $meta['other_services']               ?? '',
            'additional_details_broker'    => $meta['additional_details_broker']    ?? '',
            // Lease fee
            'lease_fee_type'               => $meta['lease_fee_type']               ?? '',
            'lease_fee_flat'               => $meta['lease_fee_flat']               ?? '',
            'lease_fee_percentage'         => $meta['lease_fee_percentage']         ?? '',
            'lease_fee_months'             => $meta['lease_fee_months']             ?? '',
            'lease_fee_other'              => $meta['lease_fee_other']              ?? '',
            // Purchase fee
            'purchase_fee_type'            => $meta['purchase_fee_type']            ?? '',
            'purchase_fee_percentage'      => $meta['purchase_fee_percentage']      ?? '',
            'purchase_fee_flat'            => $meta['purchase_fee_flat']            ?? '',
            'purchase_fee_other'           => $meta['purchase_fee_other']           ?? '',
            // Lease-option fee
            'lease_option_fee_type'        => $meta['lease_option_fee_type']        ?? '',
            'lease_option_fee_flat'        => $meta['lease_option_fee_flat']        ?? '',
            'lease_option_fee_percentage'  => $meta['lease_option_fee_percentage']  ?? '',
            'lease_option_fee_other'       => $meta['lease_option_fee_other']       ?? '',
            // Renewal fee
            'renewal_fee_type'             => $meta['renewal_fee_type']             ?? '',
            'renewal_fee_flat_free'        => $meta['renewal_fee_flat_free']        ?? '',
            'renewal_fee_first_month'      => $meta['renewal_fee_first_month']      ?? '',
            'renewal_fee_lease_value'      => $meta['renewal_fee_lease_value']      ?? '',
            'renewal_fee_no_of_months'     => $meta['renewal_fee_no_of_months']     ?? '',
            // Other broker terms
            'protection_period'            => $meta['protection_period']            ?? '',
            'early_termination_fee_option' => $meta['early_termination_fee_option'] ?? '',
            'early_termination_fee_amount' => $meta['early_termination_fee_amount'] ?? '',
            'retainer_fee_option'          => $meta['retainer_fee_option']          ?? '',
            'retainer_fee_amount'          => $meta['retainer_fee_amount']          ?? '',
            'retainer_fee_application'     => $meta['retainer_fee_application']     ?? '',
            'agency_agreement_timeframe'   => $meta['agency_agreement_timeframe']   ?? '',
            'agency_agreement_custom'      => $meta['agency_agreement_custom']      ?? '',
            'brokerage_relationship'       => $meta['brokerage_relationship']       ?? '',
            'broker_fee_timing'            => $meta['broker_fee_timing']            ?? '',

            // ── Photos, Tours & Documents ─────────────────────────────────
            'property_photos'    => $propertyPhotos,
            'video_tour_url'     => $meta['video_tour_url']    ?? '',
            'virtual_tour_url'   => $meta['virtual_tour_url']  ?? '',
            'agent_photo'        => $meta['photo']             ?? '',
            'agent_video'        => $meta['video']             ?? '',

            // ── Agent Credentials & Contact Info ──────────────────────────
            'first_name'          => $meta['first_name']          ?? '',
            'last_name'           => $meta['last_name']           ?? '',
            'phone_number'        => $meta['phone_number']        ?? '',
            'email'               => $meta['email']               ?? '',
            'agent_brokerage'     => $meta['agent_brokerage']     ?? '',
            'agent_license_number' => $meta['agent_license_number'] ?? '',
            'agent_nar_member_id' => $meta['agent_nar_member_id'] ?? '',
            'current_status'      => $meta['current_status']      ?? '',
            'video_link'          => $meta['video_link']          ?? '',

            // ── Meeting Details ────────────────────────────────────────────
            'meeting_Preference'               => $meta['meeting_Preference']               ?? '',
            'meeting_details_first_name'       => $meta['meeting_details_first_name']       ?? '',
            'meeting_details_last_name'        => $meta['meeting_details_last_name']        ?? '',
            'meeting_details_email'            => $meta['meeting_details_email']            ?? '',
            'meeting_details_phone'            => $meta['meeting_details_phone']            ?? '',
            'meeting_details_meeting_date'     => $meta['meeting_details_meeting_date']     ?? '',
            'meeting_details_meeting_time'     => $meta['meeting_details_meeting_time']     ?? '',
            'meeting_details_time_zone'        => $meta['meeting_details_time_zone']        ?? '',
            'meeting_details_instructions'     => $meta['meeting_details_instructions']     ?? '',
            'meeting_details_additional_details' => $meta['meeting_details_additional_details'] ?? '',

            // ── Property feature extras ────────────────────────────────────
            'appliances_other'           => $meta['appliances_other']           ?? '',
            'laundry_features'           => $meta['laundry_features']           ?? '',
            'other_laundry_features'     => $meta['other_laundry_features']     ?? '',
            'floor_covering'             => $parseJsonMeta('floor_covering'),
            'other_floor_covering'       => $meta['other_floor_covering']       ?? '',
            'heating_fuel'               => $parseJsonMeta('heating_fuel'),
            'other_heating_fuel'         => $meta['other_heating_fuel']         ?? '',
            'security_features'          => $parseJsonMeta('security_features'),
            'other_security_features'    => $meta['other_security_features']    ?? '',
            'bathroom_facilities'        => $meta['bathroom_facilities']        ?? '',
            'storage_space'              => $meta['storage_space']              ?? '',
            'storage_space_com_entire'   => $meta['storage_space_com_entire']   ?? '',
            'storage_space_com_single'   => $meta['storage_space_com_single']   ?? '',
            'storage_space_res_both'     => $meta['storage_space_res_both']     ?? '',
            'storage_space_res_single'   => $meta['storage_space_res_single']   ?? '',
            'included_storage_space'     => $meta['included_storage_space']     ?? '',
            'property_utilities'         => $parseJsonMeta('property_utilities'),
            'other_property_utilities'   => $meta['other_property_utilities']   ?? '',
            'building_hours'             => $meta['building_hours']             ?? '',
            'access_24_7'                => $meta['access_24_7']                ?? '',
            'room_size'                  => $meta['room_size']                  ?? '',
            'floor_covering_other'       => $meta['other_floor_covering']       ?? '',

            // ── "Other" companion text fields ──────────────────────────────
            'other_air_conditioning'     => $meta['other_air_conditioning']     ?? '',
            'other_building_features'    => $meta['other_building_features']    ?? '',
            'other_carport_needed'       => $meta['other_carport_needed']       ?? '',
            'other_current_adjacent_use' => $meta['other_current_adjacent_use'] ?? '',
            'other_current_use'          => $meta['other_current_use']          ?? '',
            'other_document_type'        => $meta['other_document_type']        ?? '',
            'other_easements'            => $meta['other_easements']            ?? '',
            'other_electrical_service'   => $meta['other_electrical_service']   ?? '',
            'other_exchange_item'        => $meta['other_exchange_item']        ?? '',
            'other_exterior_construction' => $meta['other_exterior_construction'] ?? '',
            'other_fences'               => $meta['other_fences']               ?? '',
            'other_foundation'           => $meta['other_foundation']           ?? '',
            'other_garage_needed'        => $meta['other_garage_needed']        ?? '',
            'other_heating_and_fuel'     => $meta['other_heating_and_fuel']     ?? '',
            'other_lease_for'            => $meta['other_lease_for']            ?? '',
            'other_lease_term'           => $meta['other_lease_term']           ?? '',
            'other_lease_type'           => $meta['other_lease_type']           ?? '',
            'other_licenses'             => $meta['other_licenses']             ?? '',
            'other_owner_pays'           => $meta['other_owner_pays']           ?? '',
            'other_parking_space_wrapper' => $meta['other_parking_space_wrapper'] ?? '',
            'other_preferences'          => $meta['other_preferences']          ?? '',
            'other_property_condition'   => $meta['other_property_condition']   ?? '',
            'other_property_items'       => $meta['other_property_items']       ?? '',
            'other_reason_for_sale'      => $meta['other_reason_for_sale']      ?? '',
            'other_rent_include'         => $meta['other_rent_include']         ?? '',
            'other_road_frontage'        => $meta['other_road_frontage']        ?? '',
            'other_road_surface_type'    => $meta['other_road_surface_type']    ?? '',
            'other_roof_type'            => $meta['other_roof_type']            ?? '',
            'other_sale_includes'        => $meta['other_sale_includes']        ?? '',
            'other_sewer'                => $meta['other_sewer']                ?? '',
            'other_space_classification' => $meta['other_space_classification'] ?? '',
            'other_space_type'           => $meta['other_space_type']           ?? '',
            'other_tenant_pays'          => $meta['other_tenant_pays']          ?? '',
            'other_utilities'            => $meta['other_utilities']            ?? '',
            'other_vegetation'           => $meta['other_vegetation']           ?? '',
            'other_water'                => $meta['other_water']                ?? '',
            'other_business_type'        => $meta['other_business_type']        ?? '',
            'other_services_enabled'     => $meta['other_services_enabled']     ?? '',
            'owner_pays_other'           => $meta['owner_pays_other']           ?? '',
            'tenant_pays_other'          => $meta['tenant_pays_other']          ?? '',
            'association_amenities_other'     => $meta['association_amenities_other']     ?? '',
            'association_fee_frequency_other' => $meta['association_fee_frequency_other'] ?? '',
            'association_fee_includes_other'  => $meta['association_fee_includes_other']  ?? '',
            'association_type_other'          => $meta['association_type_other']          ?? '',
            'assumable_fee_type'              => $meta['assumable_fee_type']              ?? '',
            'assumable_occupancy_other'       => $meta['assumable_occupancy_other']       ?? '',
            'initial_deposit_timeframe_other' => $meta['initial_deposit_timeframe_other'] ?? '',
            'min_lease_period_other'          => $meta['min_lease_period_other']          ?? '',
            'flood_zone_code_other'           => $meta['flood_zone_code_other']           ?? '',
            'crypto_transfer_timing_other'    => $meta['crypto_transfer_timing_other']    ?? '',
            'broker_fee_timing_other'         => $meta['broker_fee_timing_other']         ?? '',

            // ── Commercial / Lease-specific (Landlord + Tenant) ───────────
            'commercial_lease_type'            => $meta['commercial_lease_type']            ?? '',
            'commercial_lease_type_other'      => $meta['commercial_lease_type_other']      ?? '',
            'commercial_lease_type_preference' => $meta['commercial_lease_type_preference'] ?? '',
            'commercial_parking_terms'         => $meta['commercial_parking_terms']         ?? '',
            'commercial_parking_access_needs'  => $meta['commercial_parking_access_needs']  ?? '',
            'commercial_approval_conditions'   => $meta['commercial_approval_conditions']   ?? '',
            'cam_nnn_preference'               => $meta['cam_nnn_preference']               ?? '',
            'cam_nnn_additional_rent_charges'  => $meta['cam_nnn_additional_rent_charges']  ?? '',
            'gross_percentage_rent'            => $meta['gross_percentage_rent']            ?? '',
            'net_aggregate_rent'               => $meta['net_aggregate_rent']               ?? '',
            'month_percentage_rent'            => $meta['month_percentage_rent']            ?? '',
            'no_of_months'                     => $meta['no_of_months']                     ?? '',
            'flat_fee'                         => $meta['flat_fee']                         ?? '',
            'sales_tax_option_flat'            => $meta['sales_tax_option_flat']            ?? '',
            'sales_tax_option_gross'           => $meta['sales_tax_option_gross']           ?? '',
            'sales_tax_option_monthly'         => $meta['sales_tax_option_monthly']         ?? '',
            'split_payment_due'                => $meta['split_payment_due']                ?? '',
            'split_payment_due_other'          => $meta['split_payment_due_other']          ?? '',
            'space_type'                       => $meta['space_type']                       ?? '',
            'space_classification'             => $meta['space_classification']             ?? '',
            'space_features'                   => $parseJsonMeta('space_features'),
            'office_retail_sqft'               => $meta['office_retail_sqft']               ?? '',
            'flex_space_sqft'                  => $meta['flex_space_sqft']                  ?? '',
            'building_features'                => $parseJsonMeta('building_features'),
            'other_building_features_txt'      => $meta['other_building_features']          ?? '',
            'number_of_conference_rooms'       => $meta['number_of_conference_rooms']       ?? '',
            'number_of_offices'                => $meta['number_of_offices']                ?? '',
            'number_of_restrooms'              => $meta['number_of_restrooms']              ?? '',
            'number_gas_meters'                => $meta['number_gas_meters']                ?? '',
            'total_buildings'                  => $meta['total_buildings']                  ?? '',
            'total_units_on_property'          => $meta['total_units_on_property']          ?? '',
            'parking_needed'                   => $meta['parking_needed']                   ?? '',
            'signage_rights'                   => $meta['signage_rights']                   ?? '',
            'signage_request'                  => $meta['signage_request']                  ?? '',
            'permitted_use_restrictions'       => $meta['permitted_use_restrictions']       ?? '',
            'intended_business_use'            => $meta['intended_business_use']            ?? '',
            'business_type'                    => $meta['business_type']                    ?? '',
            'business_type_selected'           => $meta['business_type_selected']           ?? '',
            'rent_escalation_terms'            => $meta['rent_escalation_terms']            ?? '',
            'rent_escalation_preference'       => $meta['rent_escalation_preference']       ?? '',
            'buildout_tenant_improvement_request' => $meta['buildout_tenant_improvement_request'] ?? '',
            'tenant_improvement_buildout_terms'   => $meta['tenant_improvement_buildout_terms']   ?? '',
            'personal_guarantee_requirement'   => $meta['personal_guarantee_requirement']   ?? '',
            'personal_guarantee_preference'    => $meta['personal_guarantee_preference']    ?? '',
            'neighboring_tenants'              => $meta['neighboring_tenants']              ?? '',
            'shared_amenities'                 => $meta['shared_amenities']                 ?? '',
            'common_areas_access'              => $meta['common_areas_access']              ?? '',
            'common_areas_cleaning'            => $meta['common_areas_cleaning']            ?? '',
            'custom_lease_term'                => $meta['custom_lease_term']                ?? '',
            'lease_type'                       => $meta['lease_type']                       ?? '',
            'lease_type_other'                 => $meta['lease_type_other']                 ?? '',
            'lease_value'                      => $meta['lease_value']                      ?? '',
            'restrictions'                     => $meta['restrictions']                     ?? '',
            'zoning_allows'                    => $meta['zoning_allows']                    ?? '',

            // ── Tenant Desired Lease Preferences ──────────────────────────
            'tenant_desired_lease_length'      => $meta['tenant_desired_lease_length']      ?? '',
            'tenant_conditions'                => $meta['tenant_conditions']                ?? '',
            'first_month_rent_available'       => $meta['first_month_rent_available']       ?? '',
            'last_month_rent_available'        => $meta['last_month_rent_available']        ?? '',
            'move_in_funds_available'          => $meta['move_in_funds_available']          ?? '',
            'security_deposit_budget'          => $meta['security_deposit_budget']          ?? '',
            'utility_preference'               => $meta['utility_preference']               ?? '',
            'maintenance_preference'           => $meta['maintenance_preference']           ?? '',
            'maintenance_response_time'        => $meta['maintenance_response_time']        ?? '',
            'maintenance_handler'              => $meta['maintenance_handler']              ?? '',
            'maintenance_by'                   => $meta['maintenance_by']                   ?? '',
            'renewal_option_requested'         => $meta['renewal_option_requested']         ?? '',
            'lease_option_consideration'       => $meta['lease_option_consideration']       ?? '',
            'lease_option_fee_credit_percentage' => $meta['lease_option_fee_credit_percentage'] ?? '',
            'lease_option_fee_flat_combo'      => $meta['lease_option_fee_flat_combo']      ?? '',
            'lease_option_fee_percentage_combo' => $meta['lease_option_fee_percentage_combo'] ?? '',
            'lease_option_extension_terms'     => $meta['lease_option_extension_terms']     ?? '',
            'lease_option_maintenance'         => $meta['lease_option_maintenance']         ?? '',
            'lease_purchase_deposit'           => $meta['lease_purchase_deposit']           ?? '',
            'lease_purchase_extension_terms'   => $meta['lease_purchase_extension_terms']   ?? '',
            'lease_purchase_maintenance'       => $meta['lease_purchase_maintenance']       ?? '',
            'lease_purchase_rent_credit'       => $meta['lease_purchase_rent_credit']       ?? '',
            'lease_purchase_rent_credit_amount' => $meta['lease_purchase_rent_credit_amount'] ?? '',
            'additional_tenant_lease_terms'    => $meta['additional_tenant_lease_terms']    ?? '',
            'additional_deposit_requested'     => $meta['additional_deposit_requested']     ?? '',
            'additional_deposit_timeframe'     => $meta['additional_deposit_timeframe']     ?? '',
            'additional_deposit_timeframe_other' => $meta['additional_deposit_timeframe_other'] ?? '',
            'garage_parking_spaces_option'     => $meta['garage_parking_spaces_option']     ?? '',
            'garage_parking_spaces_option_buyer' => $meta['garage_parking_spaces_option_buyer'] ?? '',
            'guests_allowed'                   => $meta['guests_allowed']                   ?? '',
            'emotional_support_animal'         => $meta['emotional_support_animal']         ?? '',
            'screening_concerns'               => $meta['screening_concerns']               ?? '',
            'screening_concerns_explanation'   => $meta['screening_concerns_explanation']   ?? '',
            'retained_deposits'                => $meta['retained_deposits']                ?? '',
            'outstanding_balance'              => $meta['outstanding_balance']              ?? '',
            'pet_information'                  => $meta['pet_information']                  ?? '',
            'number_of_occupants'              => $meta['number_of_occupants']              ?? '',
            'additional_parcel_ids'            => $meta['additional_parcel_ids']            ?? '',
            'additional_parcels'               => $meta['additional_parcels']               ?? '',
            'total_parcel_count'               => $meta['total_parcel_count']               ?? '',

            // ── Landlord Disclosures / Legal / HOA ────────────────────────
            'landlord_disclosure_available'    => $meta['landlord_disclosure_available']    ?? '',
            'additional_landlord_lease_terms'  => $meta['additional_landlord_lease_terms']  ?? '',
            'renewal_option_offered'           => $meta['renewal_option_offered']           ?? '',
            'renewal_option_details'           => $meta['renewal_option_details']           ?? '',
            'landlord_approval_conditions'     => $meta['landlord_approval_conditions']     ?? '',
            'utility_responsibility'           => $meta['utility_responsibility']           ?? '',

            // ── Lease Fee Combo Fields ─────────────────────────────────────
            'lease_fee_flat_combo'             => $meta['lease_fee_flat_combo']             ?? '',
            'lease_fee_flat_combo_net'         => $meta['lease_fee_flat_combo_net']         ?? '',
            'lease_fee_flat_type'              => $meta['lease_fee_flat_type']              ?? '',
            'lease_fee_percentage_combo'       => $meta['lease_fee_percentage_combo']       ?? '',
            'lease_fee_percentage_combo_net'   => $meta['lease_fee_percentage_combo_net']   ?? '',
            'lease_fee_percentage_monthly_number' => $meta['lease_fee_percentage_monthly_number'] ?? '',
            'lease_fee_percentage_monthly_rent' => $meta['lease_fee_percentage_monthly_rent'] ?? '',
            'lease_fee_percentage_net'         => $meta['lease_fee_percentage_net']         ?? '',
            'lease_purchase_option_fee'        => $meta['lease_purchase_option_fee']        ?? '',
            'lease_purchase_option_fee_amount' => $meta['lease_purchase_option_fee_amount'] ?? '',

            // ── Purchase Fee Combo Fields ──────────────────────────────────
            'purchase_fee_flat_combo'          => $meta['purchase_fee_flat_combo']          ?? '',
            'purchase_fee_flat_commercial'     => $meta['purchase_fee_flat_commercial']     ?? '',
            'purchase_fee_flat_exercised'      => $meta['purchase_fee_flat_exercised']      ?? '',
            'purchase_fee_flat_type'           => $meta['purchase_fee_flat_type']           ?? '',
            'purchase_fee_gross_rent'          => $meta['purchase_fee_gross_rent']          ?? '',
            'purchase_fee_monthly_percentage'  => $meta['purchase_fee_monthly_percentage']  ?? '',
            'purchase_fee_months'              => $meta['purchase_fee_months']              ?? '',
            'purchase_fee_net_aggregate'       => $meta['purchase_fee_net_aggregate']       ?? '',
            'purchase_fee_other_commercial'    => $meta['purchase_fee_other_commercial']    ?? '',
            'purchase_fee_percentage_combo'    => $meta['purchase_fee_percentage_combo']    ?? '',
            'purchase_fee_purchase_price'      => $meta['purchase_fee_purchase_price']      ?? '',
            'purchase_fee_rental_period'       => $meta['purchase_fee_rental_period']       ?? '',
            'purchase_pice_commercial'         => $meta['purchase_pice_commercial']         ?? '',
            'purchase_type'                    => $meta['purchase_type']                    ?? '',
            'purchase_value'                   => $meta['purchase_value']                   ?? '',
            'interested_purchase_fee_type'     => $meta['interested_purchase_fee_type']     ?? '',

            // ── Landlord Broker & Expansion Fee Fields ────────────────────
            'landlord_broker_dollar_price'     => $meta['landlord_broker_dollar_price']     ?? '',
            'landlord_broker_flate_fee'        => $meta['landlord_broker_flate_fee']        ?? '',
            'landlord_broker_flate_fee_type'   => $meta['landlord_broker_flate_fee_type']   ?? '',
            'landlord_broker_other'            => $meta['landlord_broker_other']            ?? '',
            'landlord_broker_percentage_price' => $meta['landlord_broker_percentage_price'] ?? '',
            'landlord_broker_purchase_price'   => $meta['landlord_broker_purchase_price']   ?? '',
            'tenant_broker_commission_percentage' => $meta['tenant_broker_commission_percentage'] ?? '',
            'tenant_broker_commission_structure'  => $meta['tenant_broker_commission_structure']  ?? '',
            'tenant_broker_fee_structure'      => $meta['tenant_broker_fee_structure']      ?? '',
            'tenant_broker_first_month_rent'   => $meta['tenant_broker_first_month_rent']   ?? '',
            'tenant_broker_flat_fee'           => $meta['tenant_broker_flat_fee']           ?? '',
            'tenant_broker_gross_lease'        => $meta['tenant_broker_gross_lease']        ?? '',
            'tenant_broker_other'              => $meta['tenant_broker_other']              ?? '',
            'tenant_broker_percentage'         => $meta['tenant_broker_percentage']         ?? '',
            'expansion_commission_percentage'  => $meta['expansion_commission_percentage']  ?? '',
            'expansion_commission_type'        => $meta['expansion_commission_type']        ?? '',
            'expansion_custom_commission'      => $meta['expansion_custom_commission']      ?? '',
            'expansion_first_month_percentage' => $meta['expansion_first_month_percentage'] ?? '',
            'expansion_flat_fee'               => $meta['expansion_flat_fee']               ?? '',
            'expansion_gross_percentage'       => $meta['expansion_gross_percentage']       ?? '',
            'commission_structure_type'        => $meta['commission_structure_type']        ?? '',
            'commission_structure_type_fee_flat'         => $meta['commission_structure_type_fee_flat']         ?? '',
            'commission_structure_type_fee_flat_combo'   => $meta['commission_structure_type_fee_flat_combo']   ?? '',
            'commission_structure_type_fee_other'        => $meta['commission_structure_type_fee_other']        ?? '',
            'commission_structure_type_fee_percentage'   => $meta['commission_structure_type_fee_percentage']   ?? '',
            'commission_structure_type_fee_percentage_combo' => $meta['commission_structure_type_fee_percentage_combo'] ?? '',
            'broker_fee_days_after_due_event'  => $meta['broker_fee_days_after_due_event']  ?? '',
            'broker_fee_days_after_lease'      => $meta['broker_fee_days_after_lease']      ?? '',
            'broker_fee_days_after_rent'       => $meta['broker_fee_days_after_rent']       ?? '',
            'broker_fee_days_from_rent'        => $meta['broker_fee_days_from_rent']        ?? '',
            'renewal_fee_custom'               => $meta['renewal_fee_custom']               ?? '',
            'renewal_fee_percentage'           => $meta['renewal_fee_percentage']           ?? '',
            'renewal_fee_sales_tax_first_month' => $meta['renewal_fee_sales_tax_first_month'] ?? '',
            'renewal_fee_sales_tax_flat_fee'   => $meta['renewal_fee_sales_tax_flat_fee']   ?? '',
            'renewal_fee_sales_tax_lease_value' => $meta['renewal_fee_sales_tax_lease_value'] ?? '',
            'referral_percentage'              => $meta['referral_percentage']              ?? '',
            'seller_broker_leasing_fee'        => $meta['seller_broker_leasing_fee']        ?? '',
            'seller_leasing_fee_type'          => $meta['seller_leasing_fee_type']          ?? '',
            'seller_leasing_each_rental'       => $meta['seller_leasing_each_rental']       ?? '',
            'seller_leasing_gross'             => $meta['seller_leasing_gross']             ?? '',
            'seller_leasing_gross_flat_combo'  => $meta['seller_leasing_gross_flat_combo']  ?? '',
            'seller_leasing_gross_flat_net_combo' => $meta['seller_leasing_gross_flat_net_combo'] ?? '',
            'seller_leasing_gross_month_rent'  => $meta['seller_leasing_gross_month_rent']  ?? '',
            'seller_leasing_gross_no_of_months' => $meta['seller_leasing_gross_no_of_months'] ?? '',
            'seller_leasing_gross_other'       => $meta['seller_leasing_gross_other']       ?? '',
            'seller_leasing_gross_percentage_combo' => $meta['seller_leasing_gross_percentage_combo'] ?? '',
            'seller_leasing_gross_percentage_net_combo' => $meta['seller_leasing_gross_percentage_net_combo'] ?? '',
            'seller_leasing_gross_percentage_no_of_months' => $meta['seller_leasing_gross_percentage_no_of_months'] ?? '',
            'seller_leasing_gross_purchase_fee_flat_amount' => $meta['seller_leasing_gross_purchase_fee_flat_amount'] ?? '',
            'seller_leasing_gross_purchase_fee_other' => $meta['seller_leasing_gross_purchase_fee_other'] ?? '',
            'seller_leasing_gross_rental'      => $meta['seller_leasing_gross_rental']      ?? '',
            'seller_leasing_gross_ross_percentage_rent' => $meta['seller_leasing_gross_ross_percentage_rent'] ?? '',
            'seller_leasing_gross_sales_tax_first_month' => $meta['seller_leasing_gross_sales_tax_first_month'] ?? '',
            'seller_leasing_gross_sales_tax_flat_free_gross' => $meta['seller_leasing_gross_sales_tax_flat_free_gross'] ?? '',
            'seller_leasing_gross_sales_tax_option_gross' => $meta['seller_leasing_gross_sales_tax_option_gross'] ?? '',
            'seller_amortization_other'        => $meta['seller_amortization_other']        ?? '',
            'seller_down_payment_amount'       => $meta['seller_down_payment_amount']       ?? '',
            'seller_payment_frequency_other'   => $meta['seller_payment_frequency_other']   ?? '',
            'seller_lease_option_fee_credit_percent' => $meta['seller_lease_option_fee_credit_percent'] ?? '',
            'seller_lease_purchase_deposit'    => $meta['seller_lease_purchase_deposit']    ?? '',
            'seller_lease_purchase_extension_terms' => $meta['seller_lease_purchase_extension_terms'] ?? '',
            'seller_lease_purchase_maintenance' => $meta['seller_lease_purchase_maintenance'] ?? '',
            'seller_lease_purchase_rent_credit' => $meta['seller_lease_purchase_rent_credit'] ?? '',
            'seller_lease_purchase_rent_credit_amount' => $meta['seller_lease_purchase_rent_credit_amount'] ?? '',
            'seller_lease_purchase_rent_credit_type' => $meta['seller_lease_purchase_rent_credit_type'] ?? '',
            'balloon_payment'                  => $meta['balloon_payment']                  ?? '',
            'zip_code'                         => $meta['zip_code']                         ?? '',
            'gap_payment_type'                 => $meta['gap_payment_type']                 ?? '',
            'crypto_custodian_wallet'          => $meta['crypto_custodian_wallet']          ?? '',
            'crypto_transaction_fees'          => $meta['crypto_transaction_fees']          ?? '',
            'nft_gas_fees'                     => $meta['nft_gas_fees']                     ?? '',
            'nft_transfer_method'              => $meta['nft_transfer_method']              ?? '',
            'nft_valuation_method'             => $meta['nft_valuation_method']             ?? '',
            'unit_size_other'                  => $meta['unit_size_other']                  ?? '',
            'included_storage_space_com_entire' => $meta['included_storage_space_com_entire'] ?? '',
            'included_storage_space_com_single' => $meta['included_storage_space_com_single'] ?? '',
            'included_storage_space_res_both'  => $meta['included_storage_space_res_both']  ?? '',
            'included_storage_space_res_single' => $meta['included_storage_space_res_single'] ?? '',

            // ── Interested-in & Misc ───────────────────────────────────────
            'interested_in_property_management'            => $meta['interested_in_property_management']            ?? '',
            'interested_in_property_management_fee'        => $meta['interested_in_property_management_fee']        ?? '',
            'interested_in_property_management_fee_flate_free'  => $meta['interested_in_property_management_fee_flate_free']  ?? '',
            'interested_in_property_management_fee_gross_lease'  => $meta['interested_in_property_management_fee_gross_lease']  ?? '',
            'interested_in_property_management_fee_other'  => $meta['interested_in_property_management_fee_other']  ?? '',
            'interested_in_property_management_fee_rental_periord' => $meta['interested_in_property_management_fee_rental_periord'] ?? '',
            'interested_in_selling'                        => $meta['interested_in_selling']                        ?? '',
            'interested_in_selling_type'                   => $meta['interested_in_selling_type']                   ?? '',
            'interested_lease_option_agreement'            => $meta['interested_lease_option_agreement']            ?? '',
            'exchange_liens_disclosure'                    => $meta['exchange_liens_disclosure']                    ?? '',
            'exchange_liens'                               => $meta['exchange_liens']                               ?? '',
            'number_of_unit_type'                          => $meta['number_of_unit_type']                          ?? '',
            'number_of_unit_type_other'                    => $meta['number_of_unit_type_other']                    ?? '',
            'number_of_unit_other'                         => $meta['number_of_unit_other']                         ?? '',
            'number_of_occupants_allowed'                  => $meta['number_of_occupants_allowed']                  ?? '',
            'photo'                                        => $meta['photo']                                        ?? '',
        ];

        return view('agent.offer-listing-view', compact('data'));
    }

    public function offerListings(Request $request)
    {
        $uid    = Auth::id();
        $filter = $request->get('filter', 'all');

        $roleModels = [
            'seller'   => \App\Models\SellerAgentAuction::class,
            'landlord' => \App\Models\LandlordAgentAuction::class,
            'buyer'    => \App\Models\BuyerAgentAuction::class,
            'tenant'   => \App\Models\TenantAgentAuction::class,
        ];

        $all = collect();
        foreach ($roleModels as $role => $modelClass) {
            $modelClass::where('user_id', $uid)
                ->with('meta')
                ->orderByDesc('created_at')
                ->get()
                ->each(function ($auction) use ($role, &$all) {
                    $all->push($this->normalizeRoleOfferListing($auction, $role));
                });
        }
        $all = $all->sortByDesc('created_at')->values();

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

    private function normalizeRoleOfferListing($auction, string $role): array
    {
        $meta = $auction->meta->pluck('meta_value', 'meta_key');

        $isDraft    = (bool) $auction->is_draft;
        $isApproved = in_array($auction->is_approved ?? false, [true, 1, '1', 'true'], true);
        $isSold     = in_array($auction->is_sold     ?? false, [true, 1, '1', 'true'], true);

        $expiryRaw = $meta['listing_expiration'] ?? null;
        $isExpired = $expiryRaw && Carbon::now()->gt(Carbon::parse($expiryRaw));

        if ($isDraft) {
            $statusLabel = 'Draft';
            $statusClass = 'secondary';
        } elseif ($isSold) {
            $statusLabel = 'Accepted';
            $statusClass = 'success';
        } elseif (!$isApproved) {
            $statusLabel = 'Pending Review';
            $statusClass = 'warning';
        } elseif ($isExpired) {
            $statusLabel = 'Expired';
            $statusClass = 'danger';
        } else {
            $statusLabel = $meta['listing_status'] ?? 'Active';
            $statusClass = 'primary';
        }

        $address = $auction->address ?? $meta['property_address'] ?? $meta['address'] ?? '';
        $state   = $meta['property_state'] ?? $meta['state'] ?? '';
        $title   = $auction->title ?? $meta['listing_title'] ?? ($address ?: ucfirst($role) . ' Offer Listing #' . $auction->id);

        $editRouteParams = $role === 'tenant'
            ? ['auctionId' => $auction->id, 'user_type' => 'tenant']
            : ['auctionId' => $auction->id];

        $editRoute = route('offer.listing.' . $role . '.edit', $editRouteParams);
        $draftRoute = $isDraft ? $editRoute : null;

        $viewRoute = route('offer.listing.view', ['id' => $auction->id, 'role' => $role]);

        return [
            'id'           => $auction->id,
            'role'         => $role,
            'listing_id'   => $auction->listing_id ?? strtoupper($role[0]) . 'OA-' . $auction->id,
            'title'        => $title,
            'address'      => $address,
            'state'        => $state,
            'offer_type'   => $meta['offer_type'] ?? $meta['auction_type'] ?? '',
            'offer_price'  => $meta['offer_price']  ?? $meta['asking_price'] ?? null,
            'monthly_rent' => $meta['monthly_rent'] ?? $meta['rent_amount']  ?? null,
            'closing_date' => $meta['closing_date'] ?? null,
            'expiry'       => $expiryRaw,
            'created_at'   => $auction->created_at,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            '_draft'       => $isDraft,
            '_approved'    => $isApproved,
            '_sold'        => $isSold,
            '_expired'     => $isExpired,
            'draft_route'  => $draftRoute,
            'edit_route'   => $editRoute,
            'view_route'   => $viewRoute,
            'hub_route'    => route('agent.offer-listings'),
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
