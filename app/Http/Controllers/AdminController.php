<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\OfferAuction;
use App\Models\PropertyAuction;
use App\Models\SellerServiceAuction;
use App\Models\User;
use App\Notifications\OfferListingStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function dashboard()
    {
        $page_data['title'] = "Admin Dashboard";
        $page_data['total_sellers'] = User::where('user_type', 'seller')->count();
        $page_data['total_buyer'] = User::where('user_type', 'buyer')->count();
        $page_data['total_agents'] = User::where('user_type', 'agent')->count();
        return view('admin.dashboard', $page_data);
    }



    /* public function buyerAgent()
    {
        $page_data['title'] = "Buyer's Agent";
        return view('admin.buyerAgent', $page_data);
    } */



    /* public function sellerAgent()
    {
        $page_data['title'] = "Seller's Agent";
        return view('admin.sellerAgent', $page_data);
    } */



    public function userRequest()
    {
        $page_data['title'] = "Pending Approval";
        $page_data['users'] = User::where('is_approved', false)->get();
        return view('admin.user_request', $page_data);
    }

    public function userRequestApprove($id, Request $request)
    {
        if (User::whereId($id)->update(['is_approved' => true])) {
            return redirect()->back()->with('success', 'User request has been approved');
        } else {
            return redirect()->back()->with('error', 'User request has not been approved');
        }
    }

    public function addAdmin(Request $request)
    {
        if ($request->isMethod('post')) {
            $user = new User();
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->user_name = $request->user_name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->user_type = 'admin';
            $user->is_approved = $request->status;
            if ($user->save()) {
                return redirect()->back()->with('success', 'User has been added!');
            } else {
                return redirect()->back()->with('error', 'User has not been added!');
            }
        }
        $page_data['title'] = "Admin Users";
        $page_data['admin_users'] = User::where('user_type', 'admin')->get();
        return view('admin.addAdmin', $page_data);
    }

    public function updateAdmin(Request $request)
    {
        $user = User::find($request->id);
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->name = $request->first_name . ' ' . $request->last_name;
        $user->user_name = $request->user_name;
        $user->email = $request->email;
        if ($request->password != "") :
            $user->password = Hash::make($request->password);
        endif;
        $user->user_type = 'admin';
        $user->is_approved = $request->status;
        if ($user->update()) {
            return redirect()->back()->with('success', 'User has been updated!');
        } else {
            return redirect()->back()->with('error', 'User has not been updated!');
        }
    }


    public function deleteUser($id)
    {
        $user = User::find($id);
        if ($user->delete()) {
            return redirect()->back()->with('success', 'User has been deleted');
        } else {
            return redirect()->back()->with('error', 'Unable to delete User');
        }
    }

    public function inactiveUser($id)
    {
        $user = User::find($id);
        $user->update([
            'is_approved' => false
        ]);
        return redirect()->back()->with('success', 'User has been deactivated');
    }

    public function activeUser($id)
    {
        if (User::whereId($id)->update(['is_approved' => true])) {
            return redirect()->back()->with('success', 'User request has been activated!');
        } else {
            return redirect()->back()->with('error', 'User request has not been activate!');
        }
    }

    public function propertyAuctions(Request $request)
    {
        $page_data['title'] = "Seller's Property";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['propertyAuctions'] = PropertyAuction::where('is_approved', true)->get();
        } else if ($type == 2) {
            $page_data['propertyAuctions'] = PropertyAuction::where('sold', true)->get();
        } else {
            $page_data['propertyAuctions'] = PropertyAuction::where('is_approved', false)->get();
        }
        return view('admin.propertyAuctions', $page_data);
    }

    public function approvePropertyAuction($id)
    {
        $pa =  PropertyAuction::find($id);
        $pa->is_approved = true;
        if ($pa->save()) {
            return redirect()->back()->with('success', 'Property auction has been approved!');
        } else {
            return redirect()->back()->with('error', 'Property auction has not been approved!');
        }
    }

    public function criteriaAuctions(Request $request)
    {
        $page_data['title'] = "Buyer's Criteria";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = BuyerCriteriaAuction::where('is_approved', true)->get();
        } else if ($type == 2) {
            $page_data['auctions'] = BuyerCriteriaAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = BuyerCriteriaAuction::where('is_approved', false)->get();
        }
        return view('admin.criteriaAuctions', $page_data);
    }

    public function serviceAuctions(Request $request)
    {
        $page_data['title'] = "Agent Service Needed";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = AgentServiceAuction::where('is_approved', true)->get();
        } else if ($type == 2) {
            $page_data['auctions'] = AgentServiceAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = AgentServiceAuction::where('is_approved', false)->get();
        }
        // return view('admin.sellerAgentAuctions', $page_data);
        return view('admin.serviceAuctions', $page_data);
    }


    public function sellerServiceAuctions(Request $request)
    {
        $page_data['title'] = "Seller Service Auctions";
        $page_data['type'] = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['auctions'] = SellerServiceAuction::where('is_approved', true)->get();
        } else if ($type == 2) {
            $page_data['auctions'] = SellerServiceAuction::where('is_sold', true)->get();
        } else {
            $page_data['auctions'] = SellerServiceAuction::where('is_approved', false)->get();
        }
        // return view('admin.sellerAgentAuctions', $page_data);
        return view('admin.sellerServiceAuctions', $page_data);
    }

    public function approveCriteriaAuction($id)
    {
        $auction = BuyerCriteriaAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function serviceAuctionApprove($id)
    {
        $auction = AgentServiceAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function sellerServiceAuctionApprove($id)
    {
        $auction = SellerServiceAuction::find($id);
        $auction->is_approved = true;
        $auction->update();
        return redirect()->back()->with('success', 'Auction Approved Successfully!');
    }

    public function offerListings(Request $request)
    {
        $page_data['title'] = "Offer Listings";
        $page_data['type']  = $type = $request->type ?? 0;

        if ($type == 1) {
            $page_data['listings'] = OfferAuction::where('is_approved', true)->where('is_draft', false)->get();
        } else {
            $page_data['listings'] = OfferAuction::where('is_approved', false)->where('is_draft', false)->get();
        }

        return view('admin.offerListings', $page_data);
    }

    public function approveOfferListing($id)
    {
        $listing = OfferAuction::findOrFail($id);

        if ($listing->is_draft || $listing->is_approved) {
            return redirect()->back()->with('error', 'This listing is not in a pending state and cannot be approved.');
        }

        $listing->is_approved = true;
        $listing->save();

        if ($listing->user) {
            $listing->user->notify(new OfferListingStatusNotification($listing, 'approved'));
        }

        return redirect()->back()->with('success', 'Offer listing has been approved.');
    }

    public function rejectOfferListing($id)
    {
        $listing = OfferAuction::findOrFail($id);

        if ($listing->is_draft || $listing->is_approved) {
            return redirect()->back()->with('error', 'This listing is not in a pending state and cannot be rejected.');
        }

        $listing->is_approved = false;
        $listing->is_draft    = true;
        $listing->save();

        if ($listing->user) {
            $listing->user->notify(new OfferListingStatusNotification($listing, 'rejected'));
        }

        return redirect()->back()->with('success', 'Offer listing has been rejected and returned to draft.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 9 — Admin Referral Tracking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/referrals
     *
     * Read-only referral tracking view.
     * Primary dataset: accepted_bid_summaries WHERE referring_agent_id IS NOT NULL.
     * Non-referred deals are excluded.
     */
    public function referrals(Request $request)
    {
        $page_data['title'] = 'Referral Tracking';

        $validStatuses = ['pending', 'qualified', 'closed', 'paid', 'void'];
        $status = in_array($request->get('status'), $validStatuses, true)
            ? $request->get('status')
            : null;

        // ── Per-agent link summary ────────────────────────────────────────────
        $page_data['linkStats'] = DB::table('agent_referral_links')
            ->leftJoin('users', 'agent_referral_links.agent_id', '=', 'users.id')
            ->where('agent_referral_links.is_active', true)
            ->select([
                'agent_referral_links.id',
                'agent_referral_links.code',
                'agent_referral_links.click_count',
                'agent_referral_links.signup_count',
                'agent_referral_links.listing_count',
                'agent_referral_links.hire_count',
                'users.name   as agent_name',
                'users.email  as agent_email',
                'users.id     as agent_id',
            ])
            ->orderByDesc('agent_referral_links.hire_count')
            ->get();

        // ── Referred hires (accepted_bid_summaries with attribution) ──────────
        $query = DB::table('accepted_bid_summaries')
            ->whereNotNull('accepted_bid_summaries.referring_agent_id')
            ->leftJoin('users as ra', 'accepted_bid_summaries.referring_agent_id', '=', 'ra.id')
            ->leftJoin('users as ru', 'accepted_bid_summaries.tenant_user_id',      '=', 'ru.id')
            ->leftJoin('users as ha', 'accepted_bid_summaries.agent_user_id',       '=', 'ha.id')
            ->select([
                'accepted_bid_summaries.id',
                'accepted_bid_summaries.listing_type',
                'accepted_bid_summaries.listing_id',
                'accepted_bid_summaries.referring_agent_id',
                'accepted_bid_summaries.referral_source_code',
                'accepted_bid_summaries.referral_status',
                'accepted_bid_summaries.created_at',
                'accepted_bid_summaries.tenant_user_id',
                'accepted_bid_summaries.agent_user_id',
                'ra.name  as referring_agent_name',
                'ra.email as referring_agent_email',
                'ru.name  as referred_user_name',
                'ha.name  as hired_agent_name',
            ])
            ->orderByDesc('accepted_bid_summaries.created_at');

        if ($status) {
            $query->where('accepted_bid_summaries.referral_status', $status);
        }

        $page_data['rows']           = $query->paginate(50)->withQueryString();
        $page_data['filterStatus']   = $status;
        $page_data['validStatuses']  = $validStatuses;

        return view('admin.referrals', $page_data);
    }

    /**
     * POST /admin/referrals/{summary}/status
     *
     * Updates referral_status on a single accepted_bid_summaries row.
     * Only the status field is written — no other fields are touched.
     * Uses DB::table() for consistency with the referrals() query above.
     */
    public function updateReferralStatus(Request $request, $summaryId)
    {
        $allowedStatuses = ['pending', 'qualified', 'closed', 'paid', 'void'];

        $newStatus = $request->input('status');

        if (!in_array($newStatus, $allowedStatuses, true)) {
            return redirect()->back()
                ->with('error', 'Invalid status value.');
        }

        $row = DB::table('accepted_bid_summaries')
            ->whereNotNull('referring_agent_id')
            ->where('id', $summaryId)
            ->first();

        if (!$row) {
            return redirect()->back()
                ->with('error', 'Referred hire record not found.');
        }

        DB::table('accepted_bid_summaries')
            ->where('id', $summaryId)
            ->update([
                'referral_status' => $newStatus,
                'updated_at'      => now(),
            ]);

        return redirect()->back()
            ->with('success', 'Referral #' . $summaryId . ' status updated to ' . ucfirst($newStatus) . '.');
    }

    public function settings()
    {
        $page_data['title'] = "Settings";
        return view('admin.settings', $page_data);
    }
}
