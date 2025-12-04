<?php

namespace App\Http\Controllers;

use App\Models\AgentService;
use App\Models\AgentServiceAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\PropertyAuction;
use App\Models\SellerServiceAuction;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function settings()
    {
        $page_data['title'] = "Settings";
        return view('admin.settings', $page_data);
    }
}
